<?php

namespace DiPlugin\Mock;

/**
 * Mock Files
 *
 * For Apollo
 */
class MockFile
{
    public static function buildMockFiles($actions)
    {
        $mockFiles = [];
        if (count($actions['SendUDPs'])) {
            foreach ($actions['SendUDPs'] as $action) {
                self::buildMockApollo($action, $mockFiles);
            }
        }

        return $mockFiles;
    }

    public static function buildMockApollo($action, &$mockFiles)
    {
        if ($action['ActionType'] != 'SendUDP' || $action['Peer']['IP'] != '127.0.0.1' || $action['Peer']['Port'] != 9891) {
            return;
        }
        $contents = explode("\t", stripcslashes($action['Content']));
        if ($contents[0] != 1) {
            return;
        }

        $commonMockData = [
            'toggle' => [
                'namespace'        => 'gs_api',
                'name'             => $contents[1],
                'version'          => 0,
                'last_modify_time' => time(),
                'log_rate'         => 0,
                'rule'             => [
                    'subject' => 'date_time_period',
                    'verb'    => '=',
                    'objects' => [],
                ],
                'publish_to'       => [],
                'schema_version'   => '1.4.0',
            ],
        ];
        if ($contents[2]) {
            $commonMockData['toggle']['rule']['objects'][] = [
                date('Y-m-d', strtotime('-1 year')),
                date('Y-m-d', strtotime('+1 year')),
            ];
        } else {
            $commonMockData['toggle']['rule']['objects'][] = [date('Y-m-d', strtotime('+1 year')), date('Y-m-d')];
        }

        $mockFiles['/home/xiaoju/ep/as/store/toggles/' . $contents[1]] = base64_encode(json_encode($commonMockData));
        $mockFiles['/home/xiaoju/ep/as/store//toggles/' . $contents[1]] = base64_encode(json_encode($commonMockData));
    }
}
