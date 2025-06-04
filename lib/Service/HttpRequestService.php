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

use Exception;
use Psr\Log\LoggerInterface;
use OCP\ICertificateManager;

class HttpRequestService {

    private $ch;

	public function __construct(private LoggerInterface $logger, private ICertificateManager $certificateManager) {
        $this->ch = curl_init();
    }

    public function post($url, $data, $useSsl, $username = null, $password = null) {
        $jsonData = json_encode($data);

        // set CURL configuration
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);

        // if useSsl is true, we need to add a config to CURL for certificate
        if ($useSsl) {
			$certPath = $this->certificateManager->getAbsoluteBundlePath();
			if (file_exists($certPath)) {
                curl_setopt($this->ch, CURLOPT_CAINFO, $certPath);
			}
		}

        // configure request headers
        $headers = ['Content-Type: application/json'];
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        // add basic authentication credentials
        // this config will automatically encode username:password as base64 and set the Authorization header
        // Authorization will be Basic [GENERATED_BASE64_STRING]
        curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($this->ch, CURLOPT_USERPWD, "$username:$password");

        // execute the request
        $response = curl_exec($this->ch);

        // check for errors
        if (curl_errno($this->ch)) {
            $errorMessage = curl_error($this->ch);
            throw new Exception("cURL Error: $errorMessage");
        }

        // get HTTP status code
        $statusCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        // handle HTTP status code
        if ($statusCode >= 200 && $statusCode < 300) {
            return json_decode($response, true);
        } else {
            throw new Exception("HTTP Error: $statusCode");
        }
    }

    public function get($url, $useSsl, $username = null, $password = null) {

        // set CURL configuration
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);

        // if useSsl is true, we need to add a config to CURL for certificate
        if ($useSsl) {
			$certPath = $this->certificateManager->getAbsoluteBundlePath();
			if (file_exists($certPath)) {
                curl_setopt($this->ch, CURLOPT_CAINFO, $certPath);
			}
		}

        // configure request headers to return JSON instead of XML
        $headers = ['Accept: application/json'];
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        // add basic authentication credentials
        // this config will automatically encode username:password as base64 and set the Authorization header
        // Authorization will be Basic [GENERATED_BASE64_STRING]
        curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($this->ch, CURLOPT_USERPWD, "$username:$password");

        // execute the request
        $response = curl_exec($this->ch);

        // check for errors
        if (curl_errno($this->ch)) {
            $errorMessage = curl_error($this->ch);
            throw new Exception("cURL Error: $errorMessage");
        }

        // get HTTP status code
        $statusCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        // handle HTTP status code
        if ($statusCode >= 200 && $statusCode < 300) {
            return json_decode($response, true);
        } else {
            throw new Exception("HTTP Error: $statusCode");
        }
    }

    public function __destruct() {
        curl_close($this->ch);
    }
}