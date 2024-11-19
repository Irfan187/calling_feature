import { createRouter, createWebHistory } from "vue-router";

const getCallingFeatureForm = () => import("@/components/CallingComponent.vue");
const callFeatureWebRTC = () => import("@/components/CallingComponentRTC.vue");
const outgoing = () => import("@/components/OutgoingVoiceComponent.vue");
const loopbackTest = () => import("@/components/loopbackTest.vue");

const routes = [
    {
        name: "getCallingFeatureForm",
        path: "/",
        component: getCallingFeatureForm,
        meta: {
            title: `Calling Feature`,
            authRequired: false,
            isAuthLayout: true,
        },
    },
    {
        name: "callingWebRTC",
        path: "/callingWebRTC",
        component: callFeatureWebRTC,
        meta: {
            title: `Calling Feature RTC`,
            authRequired: false,
            isAuthLayout: true,
        },
    },
    {
        name: "outgoing",
        path: "/outgoing",
        component: outgoing,
        meta: {
            title: `Calling Feature`,
            authRequired: false,
            isAuthLayout: true,
        },
    },
    {
        name: "loopback-test",
        path: "/loopback-test",
        component: loopbackTest,
        meta: {
            title: `Loopback Test`,
            authRequired: false,
            isAuthLayout: true,
        },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
