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

namespace OCA\CIDgravity\Service;

use OCA\Files_External\Lib\Auth\Password\Password;
use OCA\Files_External\Lib\Backend\Backend;
use OCA\Files_External\Lib\Config\IBackendProvider;
use OCP\L10N\IFactory;
use OCP\IConfig;

class ProviderService implements IBackendProvider {
	/** @var IFactory */
	protected $lFactory;

	/** @var IConfig */
	protected $lconfig;

	public function __construct(IFactory $lFactory, private IConfig $config) {
		$this->lFactory = $lFactory;
		$this->lconfig = $config;
	}

	/**
	 * @since 9.1.0
	 * @return Backend[]
	 */
	public function getBackends() {
		$cidgravityBackend = new \OCA\CIDgravity\Service\Backend\CIDgravityBackendService(
			$this->lFactory->get('cidgravity'),
			new Password($this->lFactory->get('files_external'))
		);

		$backends = [ $cidgravityBackend ];

		// Enable CIDgravityGateway external storage only if config is set
		if ($this->lconfig->getSystemValue('cidgravity_gateway_external_storage_enabled')) {
			$cidgravityGatewayBackend = new \OCA\CIDgravity\Service\Backend\CIDgravityGatewayBackendService(
				$this->lFactory->get('cidgravityGateway'),
				new Password($this->lFactory->get('files_external'))
			);

			array_push($backends, $cidgravityGatewayBackend);
		}

		return $backends;
	}
}