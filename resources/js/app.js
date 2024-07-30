import './bootstrap';
import { createApp } from 'vue';
import { ws } from '../js/client';
import App from '@/App.vue'
const app = createApp(App);

import router from '@/router';
app.use(router);

app.mount('#app');


