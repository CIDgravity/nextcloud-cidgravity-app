<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, CIDgravity (https://cidgravity.com)
 *
 * @author Florian RUEN <florian.ruen@cidgravity.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
*/

namespace OCA\Cidgravity_Gateway\Event\Listener;

use OCA\Files_External\Service\BackendService;
use OCA\Cidgravity_Gateway\Service\ProviderService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class ExternalStoragesRegistrationListener implements IEventListener {
	/** @var BackendService */
	private $backendService;
	/** @var ProviderService */
	private $backendProvider;

	public function __construct(BackendService $backendService, ProviderService $backendProvider) {
		$this->backendService = $backendService;
		$this->backendProvider = $backendProvider;
	}

	public function handle(Event $event): void {
		$this->backendService->registerBackendProvider($this->backendProvider);
	}
}