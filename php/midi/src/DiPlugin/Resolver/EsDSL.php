<?php

/**
 * @author tanmingliang
 */

namespace DiPlugin\Resolver;

use Midi\Resolver\EsDSL as BaseDSL;
use DiPlugin\DiConfig;

class EsDSL extends BaseDSL
{
    public function apollo($apollo)
    {
        $apollo = trim($apollo);
        if (strpos($apollo, ' ') !== false) {
            list($apolloName, $value) = explode(' ', $apollo);
            $apollo = sprintf("%s\t%d\t%s", $apolloName, $value, $apolloName);
        }
        $this->must[] = ['match_phrase' => ['Actions.Content' => $apollo,]];
        return $this;
    }

    /**
     * @param array $params = [
     *     'inbound_request' => 'key_word',
     *     'inbound_response' => 'key_word',
     *     'outbound_request' => 'key_word',
     *     'outbound_response' => 'key_word',
     *     'apollo' => 'key_word',
     *     'size' => 1,
     *     'begin' => 20180101,
     *     'end' => 20181231,
     * ]
     *
     * @param bool $withContext
     * @return EsDSL
     * @throws \Midi\Exception\Exception
     */
    public function build($params, $withContext = true)
    {
        // search with record host name will be more accurate
        if ($withContext && empty($params['record-host'])) {
            $recordHost = DiConfig::getRecordHost();
            if (!empty($recordHost)) {
                $params['record-host'] = $recordHost;
            }
        }

        parent::build($params, $withContext);

        if (!empty($params['apollo'])) {
            $this->apollo($params['apollo']);
        }

        return $this;
    }
}
