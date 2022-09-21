<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Photos\Sabre\Album;

use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\Conflict;
use OCP\Files\Folder;

class PublicAlbumRoot extends AlbumRoot {
	/**
	 * @return void
	 */
	public function delete() {
		throw new Forbidden('Not allowed to delete a public album');
	}

	/**
	 * @return void
	 */
	public function setName($name) {
		throw new Forbidden('Not allowed to rename a public album');
	}

	// TODO: uncomment else it is a security hole.
	// public function copyInto($targetName, $sourcePath, INode $sourceNode): bool {
	//  throw new Forbidden('Not allowed to copy into a public album');
	// }

	/**
	 * We cannot create files in an Album
	 * We add the file to the default Photos folder and then link it there.
	 *
	 * @param [type] $name
	 * @param [type] $data
	 * @return void
	 */
	public function createFile($name, $data = null) {
		try {
			$albumOwner = $this->album->getAlbum()->getUserId();
			$photosLocation = $this->userConfigService->getConfigForUser($albumOwner, 'photosLocation');
			$photosFolder = $this->rootFolder->getUserFolder($albumOwner)->get($photosLocation);
			if (!($photosFolder instanceof Folder)) {
				throw new Conflict('The destination exists and is not a folder');
			}

			// Check for conflict and rename the file accordingly
			$newName = \basename(\OC_Helper::buildNotExistingFileName($photosLocation, $name));

			$node = $photosFolder->newFile($newName, $data);
			$this->addFile($node->getId(), $node->getOwner()->getUID());
			// Cheating with header because we are using fileID-fileName
			// https://github.com/nextcloud/server/blob/af29b978078ffd9169a9bd9146feccbb7974c900/apps/dav/lib/Connector/Sabre/FilesPlugin.php#L564-L585
			\header('OC-FileId: ' . $node->getId());
			return '"' . $node->getEtag() . '"';
		} catch (\Exception $e) {
			throw new Forbidden('Could not create file');
		}
	}


	protected function addFile(int $sourceId, string $ownerUID): bool {
		if (in_array($sourceId, $this->album->getFileIds())) {
			throw new Conflict("File $sourceId is already in the folder");
		}

		$this->albumMapper->addFile($this->album->getAlbum()->getId(), $sourceId, $ownerUID);
		return true;
	}

	// Do not reveal collaborators for public albums.
	public function getCollaborators() {
		return [];
	}
}
