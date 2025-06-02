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

namespace OCA\CIDgravity\Event\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

use OCP\IConfig;
use OCP\User\Events\UserCreatedEvent;
use OCA\Files_External\Service\GlobalStoragesService;

use OC\Files\Storage\StorageFactory;
use OC\Files\Mount\Manager;

class UserCreatedListener implements IEventListener
{
    public function __construct(private LoggerInterface $logger, private IConfig $config, private GlobalStoragesService $globalStoragesService, private Manager $mountManager, private StorageFactory $storageFactory) {}

    // Handle UserCreatedEvent to create user folder on Public Filecoin webdav
    public function handle(Event $event): void
    {
        if (!($event instanceof UserCreatedEvent)) {
            $this->logger->warning("CIDgravity - UserCreatedEvent: invalid event to create user folder on provided external storage");
            return;
        }

        $this->logger->debug("CIDgravity - UserCreatedEvent: new user created, will create associated user folder on eligible external storages",[
            "user" => json_encode($event->getUser())
        ]);

        // iterate over all external storages
        // if config for external storage autoCreateUserFolder is true, create user folder on it
        // by using directly the built-in functions, the cache table will be updated in nextcloud
        $externalStorages = $this->globalStoragesService->getStorages();

        foreach ($externalStorages as $externalStorage) {
            if ($externalStorage->getBackend()->getIdentifier() != "cidgravityGateway") {
                $this->logger->debug("CIDgravity - UserCreatedEvent: external storage not of type cidgravityGateway", [
                    "externalStorage" => json_encode($externalStorage),
                    "user" => json_encode($event->getUser())
                ]);

                continue;
            }

            if (!$externalStorage->getBackendOption('auto_create_user_folder')) {
                $this->logger->debug("CIDgravity - UserCreatedEvent: auto create user folder disabled for this external storage", [
                    "externalStorage" => json_encode($externalStorage),
                    "user" => json_encode($event->getUser())
                ]);

                continue;
            }

            // get the mount point instance for the current external storage
            // also get the storage type "DAV" here
            $mountPointInstance = $this->mountManager->find($externalStorage->getMountPoint());
            $storageClass = $externalStorage->getBackend()->getStorageClass();

            // fefore using storage backend options, we need to resolve the remote subfolder if contains $user
            $storageArguments = $externalStorage->getBackendOptions();
            $resolvedMountpoint = str_replace('$user', $event->getUser()->getUID(), $externalStorage->getBackendOption('root'));
            $storageArguments['root'] = $resolvedMountpoint;

            $this->logger->info("CIDgravity - UserCreatedEvent: will create user folder on external storage",[
                "externalStorage" => json_encode($externalStorage),
                "user" => json_encode($event->getUser()),
                "resolvedMountpoint" => $resolvedMountpoint,
            ]);

            // get storage instance of type DAV to use mkdir and other functions
            $storage = $this->storageFactory->getInstance($mountPointInstance, $storageClass, $storageArguments);

            // always use "/" here, because the $storage is already in the folder for $user
            // and we want to create the root folder
            $this->logger->debug("CIDgravity - UserCreatedEvent: check type of external storage", [
                "storage" => json_encode($storage),
                "type" => get_class($storage),
                "resolvedMountpoint" => $resolvedMountpoint,
            ]);

            // use instanceOfStorage function instead of php instanceof (not working with instanceof)
            // because instanceOfStorage can also detect the storage wrapper
            if ($storage->instanceOfStorage("OC\Files\Storage\DAV")) {
                try {
                    $fileExists = $storage->file_exists("/");

                    $this->logger->debug("CIDgravity - UserCreatedEvent: check if user folder already exists", [
                        "fileExists" => json_encode($fileExists),
                        "externalStorage" => json_encode($externalStorage),
                        "user" => json_encode($event->getUser()),
                        "resolvedMountpoint" => $resolvedMountpoint,
                    ]);

                    if (!$fileExists) {
                        $createFolder = $storage->mkdir("/");

                        $this->logger->debug("CIDgravity - UserCreatedEvent: create user folder", [
                            "createFolder" => json_encode($createFolder),
                            "externalStorage" => json_encode($externalStorage),
                            "user" => json_encode($event->getUser()),
                            "resolvedMountpoint" => $resolvedMountpoint,
                        ]);

                        if (!$createFolder) {
                            $this->logger->error("CIDgravity - UserCreatedEvent: error while creating the folder for the new user", [
                                "exception" => $e->getMessage(),
                                "externalStorage" => json_encode($externalStorage),
                                "user" => json_encode($event->getUser()),
                                "resolvedMountpoint" => $resolvedMountpoint,
                            ]);

                        } else {
                            $this->logger->debug("CIDgravity - UserCreatedEvent: user folder successfully created on external storage", [
                                "externalStorage" => json_encode($externalStorage),
                                "user" => json_encode($event->getUser()),
                                "resolvedMountpoint" => $resolvedMountpoint,
                            ]);
                        }

                    } else {
                        $this->logger->info("CIDgravity - UserCreatedEvent: user folder already exists on external storage", [
                            "externalStorage" => json_encode($externalStorage),
                            "user" => json_encode($event->getUser()),
                            "resolvedMountpoint" => $resolvedMountpoint,
                        ]);
                    }
                    
                } catch (\Exception $e) {
                    $this->logger->error("CIDgravity - UserCreatedEvent: unable to check if folder exists or to create folder", [
                        "exception" => $e->getMessage(),
                        "externalStorage" => json_encode($externalStorage),
                        "user" => json_encode($event->getUser()),
                        "resolvedMountpoint" => $resolvedMountpoint,
                    ]);
                }

            } else {
                $this->logger->debug("CIDgravity - UserCreatedEvent: external storage not of type DAV", [
                    "externalStorage" => json_encode($externalStorage),
                    "user" => json_encode($event->getUser()),
                    "resolvedMountpoint" => $resolvedMountpoint,
                ]);
            }
        }
    }
}