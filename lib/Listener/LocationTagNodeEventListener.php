<?php

namespace OCA\Photos\Listener;

use OCA\Photos\Service\LocationTagService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Cache\CacheEntryRemovedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;

class LocationTagNodeEventListener implements IEventListener {
	private LocationTagService $locationTagService;

	public function __construct(
		LocationTagService $locationTagService,
	) {
		$this->locationTagService = $locationTagService;
	}

	public function handle(Event $event): void {
		if ($event instanceof CacheEntryRemovedEvent) {
			$this->locationTagService->unTag($event->getFileId());
		}

		if ($event instanceof NodeWrittenEvent || $event instanceof NodeCreatedEvent) {
			if (!str_starts_with($event->getNode()->getMimeType(), 'image')) {
				return;
			}

			$this->locationTagService->tag($event->getNode()->getId());
		}
	}
}
