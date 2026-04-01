<?php

namespace App\Services\Conversation\Handlers;

use App\Models\ChatSession;
use App\Models\SurveyResponse;
use App\Models\Tenant;

class SurveyHandler implements HandlerInterface
{
    public function handle(ChatSession $session, string $message, string $messageType): array
    {
        $context = $session->context_data ?? [];
        $surveyId = $context['survey_id'] ?? null;
        $messageLower = mb_strtolower(trim($message));

        // Normalize legacy string steps (rating/food_quality/comment) to numeric
        $surveyStep = $context['survey_step'] ?? 0;
        if (is_string($surveyStep)) {
            $legacy = ['rating' => 0, 'food_quality' => 1, 'comment' => 2];
            $surveyStep = $legacy[$surveyStep] ?? 0;
        }

        // Skip survey entirely
        if (in_array($messageLower, ['survey_skip', 'omitir', 'no gracias', 'skip'])) {
            return $this->completeSurvey($session, $surveyId);
        }

        $questions = $this->getQuestions($session);

        if (empty($questions) || $surveyStep >= count($questions)) {
            return $this->completeSurvey($session, $surveyId);
        }

        $question = $questions[$surveyStep];
        $answer = $this->processAnswer($question, $message, $messageLower);

        if ($answer === null) {
            return $this->buildQuestionResponse($question, $context);
        }

        $this->saveAnswer($surveyId, $question['key'], $answer);

        $nextStep = $surveyStep + 1;

        if ($nextStep >= count($questions)) {
            return $this->completeSurvey($session, $surveyId);
        }

        $context['survey_step'] = $nextStep;
        $nextQuestion = $questions[$nextStep];
        $response = $this->buildQuestionResponse($nextQuestion, $context);

        // Positive/negative feedback after a rating answer
        if ($question['type'] === 'rating' && is_numeric($answer)) {
            $prefix = (int) $answer >= 4
                ? "¡Que bueno que te gustó! 😊\n\n"
                : "Gracias por tu sinceridad, trabajaremos para mejorar.\n\n";
            $response['response'] = $prefix . $response['response'];
        }

        return $response;
    }

    public function askFirstQuestion(ChatSession $session): array
    {
        $questions = $this->getQuestions($session);
        if (empty($questions)) {
            return ['response' => 'Gracias por tu compra!'];
        }
        return $this->buildQuestionResponse($questions[0], $session->context_data ?? []);
    }

    private function getQuestions(ChatSession $session): array
    {
        $tenant = Tenant::find($session->tenant_id);
        return $tenant ? $tenant->getSurveyQuestions() : Tenant::defaultSurveyQuestions();
    }

    private function processAnswer(array $question, string $message, string $messageLower): mixed
    {
        switch ($question['type']) {
            case 'rating':
                if (preg_match('/^rate_(\d+)$/', $messageLower, $m)) {
                    return (int) $m[1];
                }
                if (is_numeric($messageLower) && $messageLower >= 1 && $messageLower <= 5) {
                    return (int) $messageLower;
                }
                return null;

            case 'buttons':
                $validIds = array_map('strtolower', array_column($question['options'] ?? [], 'id'));
                if (in_array($messageLower, $validIds)) {
                    return $messageLower;
                }
                // Match by title
                foreach ($question['options'] ?? [] as $opt) {
                    if ($messageLower === mb_strtolower($opt['title'])) {
                        return strtolower($opt['id']);
                    }
                }
                return null;

            case 'text':
                if (in_array($messageLower, ['omitir', 'no', 'nada', 'ninguno'])) {
                    return '';
                }
                return $message;
        }

        return null;
    }

    private function saveAnswer(?int $surveyId, string $questionKey, mixed $answer): void
    {
        if (!$surveyId) {
            return;
        }

        $data = match ($questionKey) {
            'rating' => ['rating' => (int) $answer],
            'food_quality' => ['food_quality' => preg_replace('/^food_/', '', (string) $answer)],
            'comment' => ['comment' => $answer ?: null],
            default => [],
        };

        if (!empty($data)) {
            SurveyResponse::where('id', $surveyId)->update($data);
        }
    }

    private function buildQuestionResponse(array $question, array $context): array
    {
        $result = [
            'response' => $question['label'],
            'context_data' => $context,
        ];

        if ($question['type'] === 'text') {
            $result['response_type'] = 'buttons';
            $result['buttons'] = [['id' => 'survey_skip', 'title' => 'Omitir']];
        } else {
            $options = $question['options'] ?? [];
            $result['response_type'] = 'buttons';
            $result['buttons'] = array_slice($options, 0, 3); // WhatsApp max 3 buttons
        }

        return $result;
    }

    private function completeSurvey(ChatSession $session, ?int $surveyId): array
    {
        if ($surveyId) {
            SurveyResponse::where('id', $surveyId)->update(['completed' => true]);
        }

        $tenant = Tenant::find($session->tenant_id);
        $thankYouMsg = data_get($tenant?->settings, 'survey.thank_you_message',
            "¡Muchas gracias por tu opinión! 🙏\n\nNos ayuda a mejorar cada día. ¡Esperamos verte pronto!"
        );

        return [
            'response' => $thankYouMsg,
            'next_state' => 'greeting',
            'active_order_id' => null,
            'destroy_session' => true,
        ];
    }
}
