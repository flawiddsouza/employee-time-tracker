/**
 * We'll load jQuery and the Bootstrap jQuery plugin which provides support
 * for JavaScript based Bootstrap features such as modals and tabs. This
 * code may be modified to fit the specific needs of your application.
 */

try {
    window.Popper = require('popper.js').default
    window.$ = window.jQuery = require('jquery')

    require('bootstrap')
} catch (e) {}

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = require('axios')
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

window.Vue = require('vue')

Vue.directive('focus', {
    inserted(el) {
        el.focus()
    }
})

import Snotify from 'vue-snotify'
Vue.use(Snotify)

Vue.component('page', require('./components/Page.vue').default)
Vue.component('time-tracker', require('./components/TimeTracker.vue').default)
Vue.component('confirm-modal', require('./components/ConfirmModal.vue').default)
Vue.component('rich-text', require('./components/RichText.vue').default)

import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
Vue.use(Loading)

const app = new Vue({
    el: '#app'
})
