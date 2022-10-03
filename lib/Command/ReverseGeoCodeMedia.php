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
namespace OCA\Photos\Command;

use OCP\IConfig;
use OCP\IUserManager;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\BackgroundJob\IJobList;
use OCA\Photos\Service\ReverseGeoCoderService;
use OCA\Photos\Service\LocationTagService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReverseGeoCodeMedia extends Command {
	private ReverseGeoCoderService $rgcService;
	private IRootFolder $rootFolder;
	private LocationTagService $locationTagService;
	private IConfig $config;
	private IUserManager $userManager;

	public function __construct(
		ReverseGeoCoderService $rgcService,
		IJobList $jobList,
		IRootFolder $rootFolder,
		LocationTagService $locationTagService,
		IConfig $config,
		IUserManager $userManager
	) {
		parent::__construct();
		$this->rgcService = $rgcService;
		$this->config = $config;
		$this->jobList = $jobList;
		$this->rootFolder = $rootFolder;
		$this->locationTagService = $locationTagService;
		$this->userManager = $userManager;
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('photos:reverse-geocode-media')
			->setDescription('Reverse geocode coordinates of users\' media')
			->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Limit the geocoding to the given user.', null);
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		if (!$this->config->getSystemValueBool('enable_file_metadata', true)) {
			throw new \Exception('File metadata is not enabled.');
		}

		$this->rgcService->initCities1000KdTree();

		$userId = $input->getOption('user');
		if ($userId === null) {
			$this->scanForAllUsers();
		} else {
			$this->scanFilesForUser($userId);
		}

		return 0;
	}

	private function scanForAllUsers() {
		$users = $this->userManager->search('');

		foreach ($users as $user) {
			$this->scanFilesForUser($user->getUID());
		}
	}

	private function scanFilesForUser(string $userId) {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$this->scanFolder($userFolder);
	}

	private function scanFolder(Folder $folder) {
		foreach ($folder->getDirectoryListing() as $node) {
			if ($node instanceof Folder) {
				$this->scanFolder($node);
				continue;
			}

			if (!str_starts_with($node->getMimeType(), 'image')) {
				continue;
			}

			$this->locationTagService->tag($node->getId());
		}
	}
}
