<?php

namespace OCA\Photos\Service;

use OC\Metadata\MetadataManager;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use OCP\SystemTag\ISystemTag;

class LocationTagService {
	const LOCATION_TAG_PREFIX = 'apps:photos:location';

	private ISystemTagManager $systemTagManager;
	private ISystemTagObjectMapper $systemTagObjectMapper;
	private MetadataManager $metadataManager;
	private ReverseGeoCoderService $rgcService;

	public function __construct(
		ISystemTagManager $systemTagManager,
		ISystemTagObjectMapper $systemTagObjectMapper,
		MetadataManager $metadataManager,
		ReverseGeoCoderService $rgcService
	) {
		$this->systemTagManager = $systemTagManager;
		$this->systemTagObjectMapper = $systemTagObjectMapper;
		$this->metadataManager = $metadataManager;
		$this->rgcService = $rgcService;
	}

	public function tag(int $fileId) {
		$locationId = $this->getLocationId($fileId);
		if ($locationId === -1) {
			return;
		}

		$locationTagName = self::LOCATION_TAG_PREFIX.':'.$locationId;

		$existingLocationTag = $this->getTagForFileId($fileId);
		if ($existingLocationTag !== null && $existingLocationTag->getName() === $locationTagName) {
			return;
		}

		$this->unTag($fileId);
		$systemTag = $this->createTagIfNoExist($locationTagName);
		$this->systemTagObjectMapper->assignTags($fileId, 'files', [$systemTag->getId()]);
	}

	public function unTag(int $fileId): void {
		$locationTag = $this->getTagForFileId($fileId);

		if ($locationTag === null) {
			return;
		}

		$this->systemTagObjectMapper->unassignTags($fileId, 'files', $locationTag->getId());

		$otherFileIds = $this->systemTagObjectMapper->getObjectIdsForTags([$locationTag->getId()], 'files');
		if (count($otherFileIds) === 0) {
			$this->systemTagManager->deleteTags([$locationTag->getId()]);
		}
	}

	public function getFileIdsForUser(string $userId): array {
		// $this->systemTagManager
	}

	private function getLocationId(int $fileId): int {
		$gpsMetadata = $this->metadataManager->fetchMetadataFor('gps', [$fileId])[$fileId];
		$metadata = $gpsMetadata->getMetadata();
		$latitude = $metadata['latitude'];
		$longitude = $metadata['longitude'];

		if ($latitude === null || $longitude === null) {
			return -1;
		}

		return $this->rgcService->getLocationIdForCoordinates($latitude, $longitude);
	}

	private function createTagIfNoExist(string $tagName): ISystemTag {
		try {
			return $this->systemTagManager->getTag($tagName, false, false);
		} catch (\Exception $ex) {
			if ($ex instanceof TagNotFoundException) {
				return $this->systemTagManager->createTag($tagName, false, false);
			}

			throw $ex;
		}
	}

	private function getTagForFileId(int $fileId): ISystemTag|null {
		$tagIds = $this->systemTagObjectMapper->getTagIdsForObjects([$fileId], 'files')[$fileId];
		$tags = $this->systemTagManager->getTagsByIds($tagIds);
		$locationTags = array_filter($tags, fn (ISystemTag $tag) => str_starts_with($tag->getName(), self::LOCATION_TAG_PREFIX));
		return array_pop($locationTags);
	}
}
