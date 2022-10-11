/**
 * @copyright Copyright (c) 2022 Louis Chemineau <louis@chmn.me>
 *
 * @author Louis Chemineau <louis@chmn.me>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

export default {
	name: 'FilesByMonthMixin',

	computed: {
		/**
		 * @return {object<String, []>}
		 */
		fileIdsByMonth() {
			const filesByMonth = {}
			for (const fileId of this.fetchedFileIds) {
				const file = this.files[fileId]
				filesByMonth[file.month] = filesByMonth[file.month] ?? []
				filesByMonth[file.month].push(file.fileid)
			}

			// Sort files in sections.
			Object.keys(filesByMonth)
				.forEach(month => filesByMonth[month].sort(this.sortFilesByTimestamp))

			return filesByMonth
		},

		/**
		 * @return {string[]}
		 */
		monthsList() {
			return Object
				.keys(this.fileIdsByMonth)
				.sort((month1, month2) => month1 > month2 ? -1 : 1)
		},
	},
	methods: {
		/**
		 * @param {string} fileId1 The first file ID
		 * @param {string} fileId2 The second file ID
		 * @return {-1 | 1}
		 */
		sortFilesByTimestamp(fileId1, fileId2) {
			return this.files[fileId1].timestamp > this.files[fileId2].timestamp ? -1 : 1
		},
	},
}
