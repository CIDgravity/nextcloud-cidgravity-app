<?php

declare(strict_types=1);

namespace OCA\CIDgravity\Controller;

use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use Exception;
use OCA\CIDgravity\Service\ExternalStorageService;
use Psr\Log\LoggerInterface;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\IUserSession;

class ExternalStorageController extends Controller {

    public function __construct(string $appName, IRequest $request, private IUserSession $userSession, private ExternalStorageService $externalStorageService, private LoggerInterface $logger) {
		parent::__construct($appName, $request);
	}

    /**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * Return the external storage configuration that belongs to a specific file ID
	 * @return DataResponse
	*/
	public function getExternalStorageConfigurationForSpecificFile(int $fileId): DataResponse {
        try {

            if (!is_int($fileId)) {
                return new DataResponse(['error' => 'invalid param fileId provided'], Http::STATUS_BAD_REQUEST);
            }

            $user = $this->userSession->getUser();
            if (!$user) {
                return new DataResponse(['error' => 'user not logged in'], Http::STATUS_INTERNAL_SERVER_ERROR);
            }

            $externalStorageConfiguration = $this->externalStorageService->getExternalStorageConfigurationForSpecificFile($user, $fileId, false);

            if (!isset($externalStorageConfiguration['error'])) {
                return new DataResponse(['success' => true, 'configuration' => $externalStorageConfiguration], Http::STATUS_OK); 
            }

            return new DataResponse(['success' => false, 'error' => $externalStorageConfiguration['message']], Http::STATUS_INTERNAL_SERVER_ERROR);

        } catch (Exception $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
	}

    /**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * Return the metadata for specific file from metadata endpoint from external storage configuration
	 * @return DataResponse
	 */
	public function getMetadataForSpecificFile(string $filePath): DataResponse {
        try {
            if (empty($filePath) || !is_string($filePath) || trim($filePath) === '' || $filePath === '/') {
                return new DataResponse(['success' => false, 'error' => 'invalid or missing file path'], Http::STATUS_BAD_REQUEST);
            }

            $user = $this->userSession->getUser();
            if (!$user) {
                return new DataResponse(['success' => false, 'error' => 'user not authenticated'], Http::STATUS_UNAUTHORIZED);
            }

            $fileMetadata = $this->externalStorageService->getMetadataForSpecificFile($user, $filePath);
            return new DataResponse($fileMetadata, Http::STATUS_OK);
        } catch (Exception $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
	}
}