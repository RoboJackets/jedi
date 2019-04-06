<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class ProcessNextcloud implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $uid;
    private $is_access_active;
    private $teams;
    private $first_name;
    private $last_name;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->uid = $request->uid;
        $this->is_access_active = $request->is_access_active;
        $this->teams = $request->teams;
        $this->first_name = $request->first_name;
        $this->last_name = $request->last_name;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
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
            $response = $client->put($this->uid.'/enable');

            if (200 !== $response->getStatusCode()) {
                throw new \Exception(
                    'Nextcloud returned an unexpected HTTP response code '.$response->getStatusCode().', expected 200'
                );
            }

            $xml = simplexml_load_string($response->getBody()->getContents());

            if (false === $xml) {
                throw new \Exception('Nextcloud did not return valid XML');
            }

            $status_code = $this->getStatusCodeFromXML($xml);

            if (101 === $status_code) {
                // user probably just does not exist in Nextcloud
                return;
            }

            if (100 !== $status_code) {
                throw new \Exception(
                    'Nextcloud returned an unexpected status code '.$status_code.' in XML, expected 100 or 101'
                );
            }

            $response = $client->get($this->uid.'/groups');

            if (200 !== $response->getStatusCode()) {
                throw new \Exception(
                    'Nextcloud returned an unexpected HTTP response code '.$response->getStatusCode().', expected 200'
                );
            }

            $xml = simplexml_load_string($response->getBody()->getContents());

            if (false === $xml) {
                throw new \Exception('Nextcloud did not return valid XML');
            }

            $status_code = $this->getStatusCodeFromXML($xml);

            if (100 !== $status_code) {
                throw new \Exception(
                    'Nextcloud returned an unexpected status code '.$status_code.' in XML, expected 100'
                );
            }

            $groups_from_nc = [];

            $xpath_result = $xml->xpath('/ocs/data/groups/element');

            if (false !== $xpath_result) {
                foreach ($xpath_result as $nc_group) {
                    if (null === $nc_group) {
                        continue;
                    }
                    $groups_from_nc[] = $nc_group->__toString();
                }
            }

            unset($groups_from_nc['admin']);

            $extra_groups = array_diff($groups_from_nc, $this->teams);

            $missing_groups = array_diff($this->teams, $groups_from_nc);

            foreach ($extra_groups as $group) {
                $response = $client->delete(
                    $this->uid.'/groups',
                    [
                        'query' => 'groupid='.$group
                    ]
                );

                if (200 !== $response->getStatusCode()) {
                    throw new \Exception(
                        'Nextcloud returned an unexpected HTTP response code '.$response->getStatusCode()
                        .', expected 200'
                    );
                }

                $xml = simplexml_load_string($response->getBody()->getContents());

                if (false === $xml) {
                    throw new \Exception('Nextcloud did not return valid XML');
                }

                $status_code = $this->getStatusCodeFromXML($xml);

                if (100 !== $status_code) {
                    throw new \Exception(
                        'Nextcloud returned an unexpected status code '.$status_code.' in XML, expected 100'
                    );
                }
            }

            foreach ($missing_groups as $group) {
                $response = $client->post(
                    $this->uid.'/groups',
                    [
                        'query' => 'groupid='.$group
                    ]
                );

                if (200 !== $response->getStatusCode()) {
                    throw new \Exception(
                        'Nextcloud returned an unexpected HTTP response code '.$response->getStatusCode()
                        .', expected 200'
                    );
                }

                $xml = simplexml_load_string($response->getBody()->getContents());

                if (false === $xml) {
                    throw new \Exception('Nextcloud did not return valid XML');
                }

                $status_code = $this->getStatusCodeFromXML($xml);

                if (100 !== $status_code && 102 !== $status_code) {
                    throw new \Exception(
                        'Nextcloud returned an unexpected status code '.$status_code.' in XML, expected 100 or 102'
                    );
                }
            }
        } else {
            // disable user
            $response = $client->put($this->uid.'/disable');

            if (200 !== $response->getStatusCode()) {
                throw new \Exception(
                    'Nextcloud returned an unexpected HTTP response code '.$response->getStatusCode().', expected 200'
                );
            }

            $xml = simplexml_load_string($response->getBody()->getContents());

            if (false === $xml) {
                throw new \Exception('Nextcloud did not return valid XML');
            }

            $status_code = $this->getStatusCodeFromXML($xml);

            if (101 === $status_code) {
                // user probably just does not exist in Nextcloud
                return;
            }

            if (100 !== $status_code) {
                throw new \Exception(
                    'Nextcloud returned an unexpected status code '.$status_code.' in XML, expected 100 or 101'
                );
            }
        }
    }

    private function getStatusCodeFromXML(\SimpleXMLElement $xml): int
    {
        $status_array = $xml->xpath('/ocs/meta/statuscode');

        if (false === $status_array) {
            throw new \Exception('XPath search for status code returned false');
        }

        if (count($status_array) !== 0) {
            throw new \Exception('XPath search for status code returned '.count($status_array).' results, expected 1');
        }

        return intval($status_array[0]->__toString());
    }
}
