<?php

declare(strict_types=1);

// phpcs:disable Generic.NamingConventions.CamelCapsFunctionName.ScopeNotCamelCaps

namespace App\Jobs;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class SyncNextcloud extends SyncJob
{
    /**
     * The queue this job will run on.
     *
     * @var string
     */
    public $queue = 'nextcloud';

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $client = new Client(
            [
                'base_uri' => config('nextcloud.server').'/ocs/v1.php/cloud/users/',
                'headers' => [
                    'User-Agent' => 'JEDI on '.config('app.url'),
                    'OCS-APIRequest' => 'true',
                ],
                'auth' => [
                    config('nextcloud.username'),
                    config('nextcloud.password'),
                ],
                'allow_redirects' => false,
            ]
        );

        if ($this->is_access_active) {
            Log::info(self::class.': Enabling user '.$this->uid);

            $response = $client->put($this->uid.'/enable');

            if ($response->getStatusCode() !== 200) {
                throw new Exception(
                    'Nextcloud returned an unexpected HTTP response code '.$response->getStatusCode()
                    .', expected 200'
                );
            }

            $xml = simplexml_load_string($response->getBody()->getContents());

            if ($xml === false) {
                throw new Exception('Nextcloud did not return valid XML');
            }

            $status_code = self::getStatusCodeFromXML($xml);

            if ($status_code === 101) {
                Log::info(self::class.': User '.$this->uid.' does not exist in Nextcloud');

                return;
            }

            if ($status_code !== 100) {
                throw new Exception(
                    'Nextcloud returned an unexpected status code '.$status_code.' in XML, expected 100 or 101'
                );
            }

            Log::debug(self::class.': Getting groups for user '.$this->uid);

            $response = $client->get($this->uid.'/groups');

            if ($response->getStatusCode() !== 200) {
                throw new Exception(
                    'Nextcloud returned an unexpected HTTP response code '.$response->getStatusCode()
                    .', expected 200'
                );
            }

            $xml = simplexml_load_string($response->getBody()->getContents());

            if ($xml === false) {
                throw new Exception('Nextcloud did not return valid XML');
            }

            $status_code = self::getStatusCodeFromXML($xml);

            if ($status_code !== 100) {
                throw new Exception(
                    'Nextcloud returned an unexpected status code '.$status_code.' in XML, expected 100'
                );
            }

            $groups_from_nc = [];

            $xpath_result = $xml->xpath('/ocs/data/groups/element');

            if ($xpath_result !== false) {
                foreach ($xpath_result as $nc_group) {
                    $groups_from_nc[] = $nc_group->__toString();
                }
            }

            $extra_groups = array_diff($groups_from_nc, $this->teams);

            $missing_groups = array_diff($this->teams, $groups_from_nc);

            foreach ($extra_groups as $group) {
                if ($group === 'admin') {
                    continue;
                }

                Log::debug(self::class.': Removing group '.$group.' from '.$this->uid);

                $response = $client->delete(
                    $this->uid.'/groups',
                    [
                        'query' => [
                            'groupid' => $group,
                        ],
                    ]
                );

                if ($response->getStatusCode() !== 200) {
                    throw new Exception(
                        'Nextcloud returned an unexpected HTTP response code '.$response->getStatusCode()
                        .', expected 200'
                    );
                }

                $xml = simplexml_load_string($response->getBody()->getContents());

                if ($xml === false) {
                    throw new Exception('Nextcloud did not return valid XML');
                }

                $status_code = self::getStatusCodeFromXML($xml);

                if ($status_code !== 100) {
                    throw new Exception(
                        'Nextcloud returned an unexpected status code '.$status_code.' in XML, expected 100'
                    );
                }
            }

            foreach ($missing_groups as $group) {
                if ($group === 'admin') {
                    continue;
                }

                Log::debug(self::class.': Adding group '.$group.' to '.$this->uid);

                $response = $client->post(
                    $this->uid.'/groups',
                    [
                        'query' => [
                            'groupid' => $group,
                        ],
                    ]
                );

                if ($response->getStatusCode() !== 200) {
                    throw new Exception(
                        'Nextcloud returned an unexpected HTTP response code '.$response->getStatusCode()
                        .', expected 200'
                    );
                }

                $xml = simplexml_load_string($response->getBody()->getContents());

                if ($xml === false) {
                    throw new Exception('Nextcloud did not return valid XML');
                }

                $status_code = self::getStatusCodeFromXML($xml);

                if ($status_code !== 100 && $status_code !== 102) {
                    throw new Exception(
                        'Nextcloud returned an unexpected status code '.$status_code.' in XML, expected 100 or 102'
                    );
                }
            }

            Log::info(self::class.': Successfully enabled and synced groups for '.$this->uid);
        } else {
            Log::info(self::class.': Disabling user '.$this->uid);

            $response = $client->put($this->uid.'/disable');

            if ($response->getStatusCode() !== 200) {
                throw new Exception(
                    'Nextcloud returned an unexpected HTTP response code '.$response->getStatusCode()
                    .', expected 200'
                );
            }

            $xml = simplexml_load_string($response->getBody()->getContents());

            if ($xml === false) {
                throw new Exception('Nextcloud did not return valid XML');
            }

            $status_code = self::getStatusCodeFromXML($xml);

            if ($status_code === 101) {
                Log::info(self::class.': User '.$this->uid.' does not exist in Nextcloud');

                return;
            }

            if ($status_code !== 100) {
                throw new Exception(
                    'Nextcloud returned an unexpected status code '.$status_code.' in XML, expected 100 or 101'
                );
            }

            Log::info(self::class.': Successfully disabled '.$this->uid);
        }
    }

    private static function getStatusCodeFromXML(SimpleXMLElement $xml): int
    {
        $status_array = $xml->xpath('/ocs/meta/statuscode');

        if ($status_array === false) {
            throw new Exception('XPath search for status code returned false');
        }

        if (count($status_array) !== 1) {
            throw new Exception(
                'XPath search for status code returned '.count($status_array).' results, expected 1'
            );
        }

        return intval($status_array[0]->__toString());
    }
}
