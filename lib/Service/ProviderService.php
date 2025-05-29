<?php

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

namespace OCA\Cidgravity_Gateway\Service;

use OCA\Files_External\Lib\Auth\Password\Password;
use OCA\Files_External\Lib\Backend\Backend;
use OCA\Files_External\Lib\Config\IBackendProvider;
use OCP\L10N\IFactory;

class ProviderService implements IBackendProvider {
	/** @var IFactory */
	protected $lFactory;

	public function __construct(IFactory $lFactory) {
		$this->lFactory = $lFactory;
	}

	/**
	 * @since 9.1.0
	 * @return Backend[]
	 */
	public function getBackends() {
		$cidgravityBackend = new \OCA\Cidgravity_Gateway\Service\Backend\CidgravityBackendService(
			$this->lFactory->get('cidgravity'),
			new Password($this->lFactory->get('files_external'))
		);

		$cidgravityGatewayBackend = new \OCA\Cidgravity_Gateway\Service\Backend\CidgravityGatewayBackendService(
			$this->lFactory->get('cidgravityGateway'),
			new Password($this->lFactory->get('files_external'))
		);

		return [ $cidgravityBackend, $cidgravityGatewayBackend ];
	}
}