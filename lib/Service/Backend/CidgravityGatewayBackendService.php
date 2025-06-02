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

namespace OCA\Cidgravity\Service\Backend;

use OCA\Files_External\Lib\Backend\Backend;
use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\Auth\Password\Password;
use OCA\Files_External\Lib\DefinitionParameter;
use OCP\IL10N;

class CidgravityGatewayBackendService extends Backend {
	public function __construct(IL10N $l, Password $legacyAuth) {
		$this
			->setIdentifier('cidgravityGateway')
			->addIdentifierAlias('\OC\Files\Storage\DAV')
			->setStorageClass('\OC\Files\Storage\DAV')
			->setText($l->t('CIDgravity gateway'))
			->addParameters([
				new DefinitionParameter('host', $l->t('CIDgravity gateway URL')),
				(new DefinitionParameter('root', $l->t('Remote subfolder')))
					->setFlag(DefinitionParameter::FLAG_OPTIONAL),
				(new DefinitionParameter('secure', $l->t('Secure https://')))
					->setType(DefinitionParameter::VALUE_BOOLEAN),
				new DefinitionParameter('metadata_endpoint', $l->t('CIDgravity metadata URL')),
				(new DefinitionParameter('default_ipfs_gateway', $l->t('Default IPFS gateway URL')))
				->setType(DefinitionParameter::VALUE_TEXT)
				->setDefaultValue("https://ipfs.io/ipfs")
				->setTooltip('You can also use your custom gateway or public gateway such as https://dweb.link'),
				(new DefinitionParameter('auto_create_user_folder', $l->t('Auto create user folder')))
				->setType(DefinitionParameter::VALUE_BOOLEAN)
				->setDefaultValue(true)
				->setTooltip('Auto create on folder on this external storage when a new user is created'),
			])
			->addAuthScheme(AuthMechanism::SCHEME_PASSWORD)
			->setLegacyAuthMechanism($legacyAuth);
	}
}