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

namespace OCA\Cidgravity\Service;

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

            // Depending the external storage style, the request endpoint and method can change
            // cidgravity (using another nextcloud): GET on another nextcloud API link
            // cidgravityGateway (using webdav): POST on metadata_endpoint URL
            if (!isset($externalStorageConfiguration['error'])) {
                $response = null;

                if ($externalStorageConfiguration['is_cidgravity_gateway']) {
                    $requestBody = [
                        "verbose" => true,
                        "filePath" => $externalStorageConfiguration['filepath'],
                    ];

                    $response = $this->httpClient->post(
                        $externalStorageConfiguration['metadata_endpoint'], 
                        $requestBody,
                        $externalStorageConfiguration['ssl_enabled'],
                        $externalStorageConfiguration['user'],
                        $externalStorageConfiguration['password'],
                    );

                    // In case of success, we will happend some fileIds (local and remote)
                    // This can help some debugs in some cases
                    if ($response['success']) {
                        $response['result']['localFileId'] = $fileId;
                        return ['success' => true, 'metadata' => $response['result']];
                    } else {
                        return ['success' => false, 'error' => $response['error']];
                    }

                } else if($externalStorageConfiguration['is_cidgravity']) {
                    
                    // Note: in that case, we can't use fileId to send the request.
                    // Because Nextcloud handle file indexing in own database, the fileId in current instance is not the same in another instance.
                    // This means, if we use fileId, the file will not be found in the external nextcloud instance.
                    // To handle this, we need to call the other nextcloud on custom api route to get right fileId from filePath.
                    // And use it in the request to get all the required details.
                    $this->logger->debug("CIDgravity storage found - Will execute external call to get remote fileId from path", [
                        "localFileId" => $fileId,
                        "filePathToUse" => $externalStorageConfiguration['filepath'],
                    ]);

                    $remoteFileId = $this->getFileIdFromPathOnExternalStorageEndpoint($externalStorageConfiguration);
                    $this->logger->error("CIDgravity - Remote fileID response", [
                        "remoteFileId" => $remoteFileId,
                    ]);

                    if ($remoteFileId == -1) {
                        $this->logger->error("CIDgravity storage found - Remote fileId not found based on filePath");
                        return ['success' => false, 'error' => 'unable to find remote fileId'];
                    }

                    $this->logger->debug("CIDgravity storage found - Remote fileId found - will execute call to get metadatas", [
                        "localFileId" => $fileId,
                        "remoteFileId" => $remoteFileId,
                    ]);

                    $response = $this->httpClient->get(
                        $externalStorageConfiguration['host'] . "/ocs/v2.php/apps/cidgravity/get-file-metadata?fileId=" . $remoteFileId, 
                        $externalStorageConfiguration['ssl_enabled'],
                        $externalStorageConfiguration['user'],
                        $externalStorageConfiguration['password'],
                    );

                    // In case of success, we will happend some fileIds (local and remote)
                    // This can help some debugs in some cases
                    if ($response['success']) {
                        $response['metadata']['localFileId'] = $fileId;
                        $response['metadata']['remoteFileId'] = $remoteFileId;
                        return ['success' => true, 'metadata' => $response['metadata']];
                    } else {
                        return ['success' => false, 'error' => $response['error']];
                    }

                } else {
                    return ['success' => false, 'error' => 'unable to find external storage type to send metadata request'];
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
            if ($externalStorage->getBackend()->getIdentifier() == "cidgravityGateway" || $externalStorage->getBackend()->getIdentifier() == "cidgravity") {
                if ($includeSensitiveSettings) {
                    return $this->buildExternalStorageConfiguration($mountsForFile[0]->getInternalPath(), $externalStorage, $includeSensitiveSettings);
                }

                return $this->buildLightExternalStorageConfiguration($externalStorage);
            }

            return ['message' => 'external storage type for file ' . $fileId . ' is not a cidgravity storage', 'error' => 'external_storage_invalid_type'];

        } catch (Exception $e) {
            return ['message' => 'error getting external storage config', 'error' => $e->getMessage()];
        } catch (NotFoundException $e) {
            return ['message' => 'external storage not found for file ' . $fileId, 'error' => $e->getMessage()];
        } catch (StorageNotAvailableException $e) {
            return ['message' => 'external storage not available for file ' . $fileId, 'error' => $e->getMessage()];
        }
	}

    /**
	 * Get the fileId from entire file by searching in user folder (including connected external storages)
	 * @param IUser $nextcloudUser Nextcloud user associated with the session
	 * @param string $filePath file path to search for
	 * @return array
	 * @throws NotFoundException,Throwable
	 */
    public function getFileIdFromFilePath(IUser $nextcloudUser, string $filePath): array {
        try{
            $this->logger->info("CIDgravity - getFileIdFromFilePath: will execute request to get fileId from path", [
                "nextcloudUserID" => $nextcloudUser->getUID(),
                "filePath" => $filePath
            ]);

            $userFolder = $this->rootFolder->getUserFolder($nextcloudUser->getUID());

            // Check if file exists. Because nextcloud internal API doesn't return the right exception message.
            // To avoid strange response, we need to handle this properly.
            if (!$userFolder->nodeExists($filePath)) {
                $this->logger->error("CIDgravity - getFileIdFromFilePath: file not found");
                return [
                    'file_not_found' => true, 
                    'error' => 'file not found or not allowed to read file'
                ];
            }

            $file = $userFolder->get($filePath);

            $this->logger->debug("CIDgravity - getFileIdFromFilePath: file found", [
                "fileId" => $file->getId(),
                "filePath" => $file->getPath(),
                "fileName" => $file->getName(),
                "fileSize" => $file->getSize(),
                "fileOwner" => $file->getOwner()->getUID(),
                "isReadable" => $file->isReadable()
            ]);

            if (!$file->isReadable()) {
                $this->logger->warning("CIDgravity - getFileIdFromFilePath: user not allowed to read file");
                return [
                    'access_denied' => true,
                    'error' => 'file not found or not allowed to read file'
                ];
            }

            return ['fileId' => $file->getId()];

        } catch (NotFoundException $e) {
            $this->logger->error("CIDgravity - getFileIdFromFilePath: file not found");
            return [
                'file_not_found' => true, 
                'error' => 'file not found or not allowed to read file'
            ];

        } catch (\Throwable $e) {
            $this->logger->error("CIDgravity - getFileIdFromFilePath: unexpected error", [ "error" => $e->getMessage() ]);
            return ['error' => 'unexpected error'];
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
        $configuration['default_ipfs_gateway'] = $externalStorage->getBackendOption('default_ipfs_gateway');

        // resolve the remote subfolder config (if it contains $user, will be automatically replaced by userID)
        // this will help when sending metadata request to API endpoint
        $resolvedMountpoint = $this->userConfigHandler->handle($externalStorage->getBackendOption('root'));

        // if the mountpoint is not empty, prepend a slash
        $mountpoint = trim($resolvedMountpoint, '/');
        $filename = ltrim($fileInternalPath, '/');
        $configuration['filepath'] = $mountpoint !== '' ? ($filename !== '' ? "/$mountpoint/$filename" : "/$mountpoint") : "/$filename";

        // available only for cidgravityGateway external storage
        if ($externalStorage->getBackend()->getIdentifier() == "cidgravityGateway") {
            $configuration['metadata_endpoint'] = $externalStorage->getBackendOption('metadata_endpoint');
        }

        // check if we need to include auth settings (for metadata call only, not exposed to frontend)
        if ($includeAuthSettings) {
            $configuration['user'] = $externalStorage->getBackendOption('user');
            $configuration['password'] = $externalStorage->getBackendOption('password');
        }

        return $configuration;
    }

    /**
	 * Construct light specific configuration object from external storage configuration
	 * @param StorageConfig $externalStorage External storage to build configuration for
	 * @return array
	*/
    private function buildLightExternalStorageConfiguration(StorageConfig $externalStorage): array {
        $configuration = [];

        $isCidgravityGateway = $externalStorage->getBackend()->getIdentifier() == "cidgravityGateway";
        $configuration['is_cidgravity_gateway'] = $isCidgravityGateway;
        $configuration['is_cidgravity'] = $externalStorage->getBackend()->getIdentifier() == "cidgravity";
        $configuration['default_ipfs_gateway'] = $externalStorage->getBackendOption('default_ipfs_gateway');

        return $configuration;
    }

    /**
	 * Get the fileId from path using an API call to specific URL and return the fileId
	 * @param StorageConfig $externalStorage External storage to build configuration for
	 * @return int
	*/
    private function getFileIdFromPathOnExternalStorageEndpoint(array $externalStorageConfiguration): int {
        $filePath = $externalStorageConfiguration['filepath'];

        $response = $this->httpClient->get(
            $externalStorageConfiguration['host'] . "/ocs/v2.php/apps/cidgravity/get-fileid-from-path?path=" . $filePath, 
            $externalStorageConfiguration['ssl_enabled'],
            $externalStorageConfiguration['user'],
            $externalStorageConfiguration['password'],
        );

        if ($response['success']) {
            return $response['data']['fileId'];
        } else {
            $this->logger->error("CIDgravity - getFileIdFromPathOnExternalStorageEndpoint: unable to get fileId", [ "error" => $response['error'] ]);
            return -1;
        }
    }
}