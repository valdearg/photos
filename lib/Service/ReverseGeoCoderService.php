<?php

declare(strict_types=1);
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Photos\Service;

use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use Hexogen\KDTree\FSTreePersister;
use Hexogen\KDTree\FSKDTree;
use Hexogen\KDTree\KDTree;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemList;
use Hexogen\KDTree\ItemFactory;
use Hexogen\KDTree\NearestSearch;
use Hexogen\KDTree\Point;

class ReverseGeoCoderService {
	private ISimpleFolder $geoNameFolder;
	private ?NearestSearch $fsSearcher = null;
	private IClientService $clientService;

	public function __construct(
		IAppData $appData,
		IClientService $clientService
	) {
		$this->clientService = $clientService;

		try {
			$this->geoNameFolder = $appData->getFolder("geonames");
		} catch (\Exception $ex) {
			if ($ex instanceof NotFoundException) {
				$this->geoNameFolder = $appData->newFolder("geonames");
			}

			throw $ex;
		}
	}

	// TODO load locations mapping
	// names in English for admin divisions. Columns: code, name, name ascii, geonameid
	// $this->downloadFile("http://download.geonames.org/export/dump/admin1CodesASCII.txt", "admin1CodesASCII.txt", $force);
	// names for administrative subdivision 'admin2 code' (UTF8), Format : concatenated codes <tab>name <tab> asciiname <tab> geonameId
	// $this->downloadFile("http://download.geonames.org/export/dump/admin2Codes.txt", "admin2Codes.txt", $force);

	public function getLocationIdForCoordinates(float $latitude, float $longitude): int {
		if ($this->fsSearcher === null) {
			$this->fsSearcher = $this->loadKDTree("cities1000.bin");
		}

		$result = $this->fsSearcher->search(new Point([$latitude, $longitude]), 1);
		return $result[0]->getId();
	}

	// All cities with a population > 1000 or seats of adm div down to PPLA3 (ca 130.000), see 'geoname' table for columns
	public function initCities1000KdTree(bool $force = false) {
		if ($this->geoNameFolder->fileExists('cities1000.bin') && !$force) {
			return;
		}

		// Download zip file to a tmp file.
		$response = $this->clientService->newClient()->get("http://download.geonames.org/export/dump/cities1000.zip");
		$cities1000ZipTmpFileName = tempnam(sys_get_temp_dir(), "nextcloud_photos_");
		file_put_contents($cities1000ZipTmpFileName, $response->getBody());

		// Unzip the txt file into a string.
		$zip = new \ZipArchive;
		$res = $zip->open($cities1000ZipTmpFileName);
		if ($res !== true) {
			throw new \Exception("Fail to unzip location file: $res", $res);
		}
		$cities1000TxtString = $zip->getFromName('cities1000.txt');
		$zip->close();

		$tree = $this->buildKDTree($cities1000TxtString);

		// Persiste KDTree in app data.
		$persister = new FSTreePersister('/');
		$kdTreeTmpFileName = tempnam(sys_get_temp_dir(), "nextcloud_photos_");
		$persister->convert($tree, $kdTreeTmpFileName);
		$kdTreeString = file_get_contents($kdTreeTmpFileName);
		$this->geoNameFolder->newFile('cities1000.bin', $kdTreeString);
	}

	private function buildKDTree(string $fileContent): KDTree {
		$itemList = new ItemList(2);

		$lines = str_getcsv($fileContent, "\n", '');

		foreach ($lines as $line) {
			$fields = str_getcsv($line, '	', '');
			$geocode = (int)$fields[0];
			$latitude = $fields[4];
			$longitude = $fields[5];

			$itemList->addItem(new Item($geocode, [$latitude, $longitude]));
		}

		return new KDTree($itemList);
	}

	private function loadKDTree($fileName): NearestSearch {
		$kdTreeFileContent = $this->geoNameFolder->getFile($fileName)->getContent();
		$kdTreeTmpFileName = tempnam(sys_get_temp_dir(), "nextcloud_photos_");
		file_put_contents($kdTreeTmpFileName, $kdTreeFileContent);
		$fsTree = new FSKDTree($kdTreeTmpFileName, new ItemFactory());
		return new NearestSearch($fsTree);
	}
}
