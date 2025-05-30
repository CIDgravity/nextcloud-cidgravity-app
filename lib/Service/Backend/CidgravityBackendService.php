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

namespace OCA\Cidgravity_Gateway\Service\Backend;

use OCA\Files_External\Lib\Backend\Backend;
use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\Auth\Password\Password;
use OCA\Files_External\Lib\DefinitionParameter;
use OCP\IL10N;
use OCP\IConfig;

class CidgravityBackendService extends Backend {
	public function __construct(IL10N $l, Password $legacyAuth, private IConfig $config) {
		$this
			->setIdentifier('cidgravity')
			->addIdentifierAlias('\OC\Files\Storage\OwnCloud')
			->setStorageClass('\OCA\Files_External\Lib\Storage\OwnCloud')
			->setText($l->t('CIDgravity'))
			->addParameters([
				(new DefinitionParameter('host', $l->t('URL')))
					->setFlag(DefinitionParameter::FLAG_HIDDEN)
					->setType(DefinitionParameter::VALUE_TEXT)
					->setDefaultValue($config->getSystemValue('cidgravity')['default_host']),
				(new DefinitionParameter('secure', $l->t('Secure https://')))
					->setType(DefinitionParameter::VALUE_BOOLEAN)
					->setFlag(DefinitionParameter::FLAG_HIDDEN)
					->setDefaultValue($config->getSystemValue('cidgravity')['default_ssl_enabled']),
			])
			->addAuthScheme(AuthMechanism::SCHEME_PASSWORD)
			->setLegacyAuthMechanism($legacyAuth);
	}
}