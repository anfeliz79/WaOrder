import '../css/app.css';

import { createApp } from 'vue';
import MenuItemPage from './Pages/Public/MenuItem.vue';
import MenuBrowsePage from './Pages/Public/MenuBrowse.vue';

const el = document.getElementById('menu-app');

if (el) {
    const token = el.dataset.token;
    const page = el.dataset.page;

    const component = page === 'menu' ? MenuBrowsePage : MenuItemPage;

    createApp(component, { token }).mount(el);
}
