/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { generateUrl } from '@nextcloud/router'
import Router from 'vue-router'
import Vue from 'vue'

import isMapsInstalled from '../services/IsMapsInstalled.js'
import areTagsInstalled from '../services/AreTagsInstalled.js'
import { imageMimes, videoMimes } from '../services/AllowedMimes.js'

import isRecognizeInstalled from '../services/IsRecognizeInstalled.js'

const Folders = () => import('../views/Folders')
const Albums = () => import('../views/Albums')
const AlbumContent = () => import('../views/AlbumContent')
const SharedAlbums = () => import('../views/SharedAlbums')
const SharedAlbumContent = () => import('../views/SharedAlbumContent')
const PublicAlbumContent = () => import('../views/PublicAlbumContent')
const Tags = () => import('../views/Tags')
const TagContent = () => import('../views/TagContent')
const Timeline = () => import('../views/Timeline')
const Faces = () => import('../views/Faces')
const FaceContent = () => import('../views/FaceContent')

Vue.use(Router)

let mapsPath = generateUrl('/apps/maps')
if (!isMapsInstalled) {
	mapsPath = generateUrl('/settings/apps/integration/maps')
}

/**
 * Parse the path of a route : join the elements of the array and return a single string with slashes
 * + always lead current path with a slash
 *
 * @param {string | Array} path path arguments to parse
 * @return {string}
 */
const parsePathParams = (path) => {
	return `/${Array.isArray(path) ? path.join('/') : path || ''}`
}

const router = new Router({
	mode: 'history',
	// if index.php is in the url AND we got this far, then it's working:
	// let's keep using index.php in the url
	base: generateUrl('/apps/photos'),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/',
			component: Timeline,
			name: 'all_media',
			props: route => ({
				rootTitle: t('photos', 'All your media'),
			}),
		},
		{
			path: '/photos',
			component: Timeline,
			name: 'photos',
			props: route => ({
				rootTitle: t('photos', 'Photos'),
				mimesType: imageMimes,
			}),
		},
		{
			path: '/videos',
			component: Timeline,
			name: 'videos',
			props: route => ({
				rootTitle: t('photos', 'Videos'),
				mimesType: videoMimes,
			}),
		},
		{
			path: '/albums',
			component: Albums,
			name: 'albums',
		},
		{
			path: '/albums/:albumName*',
			component: AlbumContent,
			name: 'albums',
			props: route => ({
				albumName: route.params.albumName,
			}),
		},
		{
			path: '/sharedalbums',
			component: SharedAlbums,
			name: 'sharedAlbums',
		},
		{
			path: '/sharedalbums/:albumName*',
			component: SharedAlbumContent,
			name: 'sharedAlbums',
			props: route => ({
				albumName: route.params.albumName,
			}),
		},
		{
			path: '/public/:userId/:token',
			component: PublicAlbumContent,
			name: 'publicAlbums',
			props: route => ({
				userId: route.params.userId,
				token: route.params.token,
			}),
		},
		{
			path: '/folders/:path*',
			component: Folders,
			name: 'folders',
			props: route => ({
				path: parsePathParams(route.params.path),
				// if path is empty
				isRoot: !route.params.path,
				rootTitle: t('photos', 'Folders'),
			}),
		},
		{
			path: '/shared/:path*',
			component: Folders,
			name: 'shared',
			props: route => ({
				path: parsePathParams(route.params.path),
				// if path is empty
				isRoot: !route.params.path,
				rootTitle: t('photos', 'Shared with you'),
				showShared: true,
			}),
		},
		{
			path: '/favorites',
			component: Timeline,
			name: 'favorites',
			props: route => ({
				rootTitle: t('photos', 'Favorites'),
				onlyFavorites: true,
			}),
		},
		{
			path: '/tags/',
			component: Tags,
			name: 'tags',
			redirect: !areTagsInstalled ? { name: 'timeline' } : null,
			props: route => ({
				path: '',
				isRoot: !route.params.path,
				rootTitle: t('photos', 'Tagged photos'),
			}),
		},
		{
			path: '/tags/:path',
			component: TagContent,
			name: 'tagcontent',
			redirect: !areTagsInstalled ? { name: 'timeline' } : null,
			props: route => ({
				path: `${route.params.path ? route.params.path : ''}`,
			}),
		},
		{
			path: '/maps',
			name: 'maps',
			// router-link doesn't support external url, let's force the redirect
			beforeEnter() {
				window.open(mapsPath, '_blank')
			},
		},
		{
			path: '/thisday',
			name: 'thisday',
			component: Timeline,
			props: route => ({
				rootTitle: t('photos', 'On this day'),
				onThisDay: true,
			}),
		},
		{
			path: '/faces',
			name: 'faces',
			component: Faces,
			...((!isRecognizeInstalled) && {
				beforeEnter() {
					const recognizeInstallLink = generateUrl('/settings/apps/installed/recognize')
					window.open(recognizeInstallLink, '_blank')
				},
			}),
		},
		{
			path: '/faces/:faceName',
			name: 'facecontent',
			component: FaceContent,
			props: route => ({
				rootTitle: route.params.faceName,
				faceName: route.params.faceName,
			}),
		},
	],
})

export default router
