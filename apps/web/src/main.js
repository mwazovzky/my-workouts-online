import '@/assets/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from '@/App.vue';
import { createAppRouter } from '@/router';
import { useAuthStore } from '@/stores/auth';
import { useLocaleStore } from '@/stores/locale';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);

document.documentElement.lang = useLocaleStore().locale;
useAuthStore();

app.use(createAppRouter());
app.mount('#app');
