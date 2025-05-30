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

use Exception;
use OCP\IUser;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCA\Files_External\Service\GlobalStoragesService;
use Psr\Log\LoggerInterface;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Config\UserPlaceholderHandler;

use OCA\Files_External\NotFoundException;
use OCP\Files\StorageNotAvailableException;

use OCP\IRequest;
use OCP\IUserManager;
use OCP\Share\IManager;

class ExternalStorageService {

    private UserPlaceholderHandler $userConfigHandler;

	public function __construct(private LoggerInterface $logger, private IUserMountCache $userMountCache, private IRootFolder $rootFolder, private GlobalStoragesService $globalStoragesService, private HttpRequestService $httpClient,
    private IRequest $request, private IUserManager $userManager, private IManager $shareManager) {
        $userSession = \OC::$server->getUserSession();
        $this->userConfigHandler = new UserPlaceholderHandler($userSession, $shareManager, $request, $userManager);
    }

    /**
	 * Get the metadata from the external storage metadata endpoint for specific file
	 * @param IUser $nextcloudUser Nextcloud user associated with the session
	 * @param int $fileId File ID to search for
	 * @return array
	 * @throws Exception
	 */
    public function getMetadataForSpecificFile(IUser $nextcloudUser, int $fileId): array {
        try {
            $externalStorageConfiguration = $this->getExternalStorageConfigurationForSpecificFile($nextcloudUser, $fileId, true);

            if (!isset($externalStorageConfiguration['error'])) {
                $requestBody = [
                    "verbose" => true,
                    "filePath" => $externalStorageConfiguration['filepath'],
                ];

                // TODO: update metadata_endpoint to use different url if cidgravity storage instead of cidgravityGateway
                $response = $this->httpClient->post(
                    $externalStorageConfiguration['metadata_endpoint'], 
                    $requestBody,
                    $externalStorageConfiguration['ssl_enabled'],
                    $externalStorageConfiguration['user'],
                    $externalStorageConfiguration['password'],
                );

                if ($response['success']) {
                    return ['success' => true, 'metadata' => $response['result']];
                } else {
                    return ['success' => false, 'error' => $response['error']];
                }

            } else {
                return ['success' => false, 'error' => 'unable to find external storage configuration'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => 'error getting metadata for file ' . $fileId];
        }
	}

    /**
	 * Get the external storage configuration to which a specific fileId belongs to
	 * @param IUser $nextcloudUser Nextcloud user associated with the session
	 * @param int $fileId File ID to search for
	 * @return array
	 * @throws Exception
	 */
    public function getExternalStorageConfigurationForSpecificFile(IUser $nextcloudUser, int $fileId, bool $includeSensitiveSettings): array {
        try {
            $mountsForFile = $this->userMountCache->getMountsForFileId($fileId, $nextcloudUser->getUID());

            if (empty($mountsForFile)) {
                return ['message' => 'no external storage found for file ' . $fileId, 'error' => 'external_storage_not_found'];
            }

            // get configuration for external storage from ID
            $externalStorage = $this->globalStoragesService->getStorage($mountsForFile[0]->getMountId());

            // check external storage type is a CIDgravity storage (works for cidgravityGateway or cidgravity types)
            // if not, it means storage not found (for our use case)
            if ($externalStorage->getBackend()->getIdentifier() != "cidgravityGateway" || $externalStorage->getBackend()->getIdentifier() != "cidgravity") {
                return ['message' => 'external storage type for file ' . $fileId . ' is not a cidgravity storage', 'error' => 'external_storage_invalid_type'];
            }

            if ($includeSensitiveSettings) {
                return $this->buildExternalStorageConfiguration($mountsForFile[0]->getInternalPath(), $externalStorage, $includeSensitiveSettings);
            }

            return $this->buildLightExternalStorageConfiguration($externalStorage);

        } catch (Exception $e) {
            return ['message' => 'error getting external storage config', 'error' => $e->getMessage()];
        } catch (NotFoundException $e) {
            return ['message' => 'external storage not found for file ' . $fileId, 'error' => $e->getMessage()];
        } catch (StorageNotAvailableException $e) {
            return ['message' => 'external storage not available for file ' . $fileId, 'error' => $e->getMessage()];
        }
	}

    /**
	 * Construct specific configuration object from external storage configuration to avoid expose sensitive data (such as password ...)
     * @param string $fileInternalPath File internal path (without the external storage mount point, only path after)
     * @param bool $includeAuthSettings should include username and password in the returned configuration or not
	 * @param StorageConfig $externalStorage External storage to build configuration for
	 * @return array
	*/
    private function buildExternalStorageConfiguration(string $fileInternalPath, StorageConfig $externalStorage, bool $includeAuthSettings): array {
        $configuration = [];
        $configuration['is_cidgravity_gateway'] = $externalStorage->getBackend()->getIdentifier() == "cidgravityGateway";
        $configuration['is_cidgravity'] = $externalStorage->getBackend()->getIdentifier() == "cidgravity";
        $configuration['id'] = $externalStorage->getId();
        $configuration['host'] = $externalStorage->getBackendOption('host');
        $configuration['mountpoint'] = $externalStorage->getMountPoint();
        $configuration['ssl_enabled'] = $externalStorage->getBackendOption('secure');
        
        // available only for cidgravityGateway external storage
        if ($externalStorage->getBackend()->getIdentifier() == "cidgravityGateway") {
            $configuration['metadata_endpoint'] = $externalStorage->getBackendOption('metadata_endpoint');
            $configuration['default_ipfs_gateway'] = $externalStorage->getBackendOption('default_ipfs_gateway');
        }

        // check if we need to include auth settings (for metadata call only, not exposed to frontend)
        if ($includeAuthSettings) {
            $configuration['user'] = $externalStorage->getBackendOption('user');
            $configuration['password'] = $externalStorage->getBackendOption('password');
        }

        // TODO: maybe need to update this code to make it works with cidgravity external storage?
        // resolve the remote subfolder config (if it contains $user, will be automatically replaced by userID)
        // this will help when sending metadata request to API endpoint
        $resolvedMountpoint = $this->userConfigHandler->handle($externalStorage->getBackendOption('root'));

        // if the mountpoint is not empty, prepend a slash
        $mountpoint = trim($resolvedMountpoint, '/');
        $filename = ltrim($fileInternalPath, '/');
        $configuration['filepath'] = $mountpoint !== '' ? ($filename !== '' ? "/$mountpoint/$filename" : "/$mountpoint") : "/$filename";

        return $configuration;
    }

    /**
	 * Construct light specific configuration object from external storage configuration
	 * @param StorageConfig $externalStorage External storage to build configuration for
	 * @return array
	*/
    private function buildLightExternalStorageConfiguration(StorageConfig $externalStorage): array {
        $configuration = [];
        $configuration['is_cidgravity_gateway'] = $externalStorage->getBackend()->getIdentifier() == "cidgravityGateway";
        $configuration['is_cidgravity'] = $externalStorage->getBackend()->getIdentifier() == "cidgravity";

        // available only for cidgravityGateway external storage
        if ($externalStorage->getBackend()->getIdentifier() == "cidgravityGateway") {
            $configuration['default_ipfs_gateway'] = $externalStorage->getBackendOption('default_ipfs_gateway');
        }

        return $configuration;
    }
}