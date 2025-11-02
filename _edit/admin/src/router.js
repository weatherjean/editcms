import { createRouter, createWebHashHistory } from 'vue-router'
import ConfigView from './views/ConfigView.vue'
import ContentListView from './views/ContentListView.vue'
import ContentEditorView from './views/ContentEditorView.vue'
import MediaView from './views/MediaView.vue'
import UsersView from './views/UsersView.vue'

const routes = [
  {
    path: '/',
    redirect: '/config'
  },
  {
    path: '/config',
    name: 'config',
    component: ConfigView
  },
  {
    path: '/media',
    name: 'media',
    component: MediaView
  },
  {
    path: '/users',
    name: 'users',
    component: UsersView
  },
  {
    path: '/:type',
    name: 'content-list',
    component: ContentListView,
    props: true
  },
  {
    path: '/:type/create',
    name: 'content-create',
    component: ContentEditorView,
    props: true
  },
  {
    path: '/:type/:id',
    name: 'content-edit',
    component: ContentEditorView,
    props: true
  }
]

const router = createRouter({
  history: createWebHashHistory('/_edit/admin/'),
  routes
})

export default router
