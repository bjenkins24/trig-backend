Nova.booting((Vue, router, store) => {
  Vue.component('index-impersonation', require('./components/IndexField'))
  Vue.component('detail-impersonation', require('./components/DetailField'))
  Vue.component('form-impersonation', require('./components/FormField'))
})
