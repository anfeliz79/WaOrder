<?php

namespace App\Services\Conversation\Handlers;

use App\Models\ChatSession;
use App\Models\SurveyResponse;

class SurveyHandler implements HandlerInterface
{
    public function handle(ChatSession $session, string $message, string $messageType): array
    {
        $context = $session->context_data ?? [];
        $surveyStep = $context['survey_step'] ?? 'rating';
        $surveyId = $context['survey_id'] ?? null;

        $messageLower = mb_strtolower(trim($message));

        // Skip survey
        if (in_array($messageLower, ['survey_skip', 'omitir', 'no gracias', 'skip'])) {
            return $this->completeSurvey($surveyId, 'Gracias por tu compra! Esperamos verte pronto.');
        }

        switch ($surveyStep) {
            case 'rating':
                return $this->handleRating($session, $messageLower, $surveyId, $context);
            case 'food_quality':
                return $this->handleFoodQuality($session, $messageLower, $surveyId, $context);
            case 'comment':
                return $this->handleComment($session, $message, $surveyId);
            default:
                return $this->askRating();
        }
    }

    public function askRating(): array
    {
        return [
            'response' => "Nos encantaria saber tu opinion!\n\nDel 1 al 5, como calificarias tu experiencia?",
            'response_type' => 'buttons',
            'buttons' => [
                ['id' => 'rate_5', 'title' => '⭐⭐⭐⭐⭐ (5)'],
                ['id' => 'rate_4', 'title' => '⭐⭐⭐⭐ (4)'],
                ['id' => 'rate_3', 'title' => '⭐⭐⭐ (3 o menos)'],
            ],
        ];
    }

    private function handleRating(ChatSession $session, string $message, ?int $surveyId, array $context): array
    {
        $rating = null;

        if (in_array($message, ['rate_5', '5'])) $rating = 5;
        elseif (in_array($message, ['rate_4', '4'])) $rating = 4;
        elseif (in_array($message, ['rate_3', '3'])) $rating = 3;
        elseif ($message === '2') $rating = 2;
        elseif ($message === '1') $rating = 1;

        if ($rating === null) {
            return $this->askRating();
        }

        if ($surveyId) {
            SurveyResponse::where('id', $surveyId)->update(['rating' => $rating]);
        }

        $context['survey_step'] = 'food_quality';

        $responseText = $rating >= 4
            ? "Que bueno que te gusto! 😊"
            : "Gracias por tu sinceridad, trabajaremos para mejorar.";

        return [
            'response' => "{$responseText}\n\nComo estuvo la calidad de la comida?",
            'response_type' => 'buttons',
            'buttons' => [
                ['id' => 'food_excellent', 'title' => 'Excelente'],
                ['id' => 'food_good', 'title' => 'Buena'],
                ['id' => 'food_regular', 'title' => 'Regular'],
            ],
            'context_data' => $context,
        ];
    }

    private function handleFoodQuality(ChatSession $session, string $message, ?int $surveyId, array $context): array
    {
        $quality = null;

        if (in_array($message, ['food_excellent', 'excelente'])) $quality = 'excellent';
        elseif (in_array($message, ['food_good', 'buena', 'bien'])) $quality = 'good';
        elseif (in_array($message, ['food_regular', 'regular', 'normal'])) $quality = 'regular';
        elseif (in_array($message, ['food_bad', 'mala', 'mal'])) $quality = 'bad';

        if ($quality === null) {
            return [
                'response' => "Selecciona una opcion:",
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'food_excellent', 'title' => 'Excelente'],
                    ['id' => 'food_good', 'title' => 'Buena'],
                    ['id' => 'food_regular', 'title' => 'Regular'],
                ],
                'context_data' => $context,
            ];
        }

        if ($surveyId) {
            SurveyResponse::where('id', $surveyId)->update(['food_quality' => $quality]);
        }

        $context['survey_step'] = 'comment';

        return [
            'response' => "Tienes algun comentario adicional? Puedes escribirlo o presionar Omitir.",
            'response_type' => 'buttons',
            'buttons' => [
                ['id' => 'survey_skip', 'title' => 'Omitir'],
            ],
            'context_data' => $context,
        ];
    }

    private function handleComment(ChatSession $session, string $message, ?int $surveyId): array
    {
        $messageLower = mb_strtolower(trim($message));

        if (!in_array($messageLower, ['survey_skip', 'omitir', 'no', 'nada'])) {
            if ($surveyId) {
                SurveyResponse::where('id', $surveyId)->update(['comment' => $message]);
            }
        }

        return $this->completeSurvey($surveyId, "Muchas gracias por tu opinion! 🙏\n\nNos ayuda a mejorar cada dia. Esperamos verte pronto!");
    }

    private function completeSurvey(?int $surveyId, string $message): array
    {
        if ($surveyId) {
            SurveyResponse::where('id', $surveyId)->update(['completed' => true]);
        }

        return [
            'response' => $message,
            'next_state' => 'greeting',
            'active_order_id' => null,
            'destroy_session' => true,
        ];
    }
}
