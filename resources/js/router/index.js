import { createRouter, createWebHistory } from 'vue-router';


const getCallingFeatureForm = () => import("@/components/CallingComponent.vue");


const routes = [
    // ---------------------------
    // Home Route
    // ---------------------------
    {
        name: "getCallingFeatureForm",
        path: "/getCallingFeatureForm",
        component: getCallingFeatureForm,
        meta: {
            title: `Login`,
            authRequired: false,
            isAuthLayout: true,
        }
    }
];


const router = createRouter({
    history: createWebHistory(),
    routes,
});


export default router;