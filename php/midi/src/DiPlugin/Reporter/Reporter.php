<?php
/**
 * @author tanmingliang
 */

namespace DiPlugin\Reporter;

use Midi\Midi;
use Midi\Parser\ParseReplayedInterface;
use Midi\Reporter\Reporter as BaseReporter;
use Midi\Koala\Replayed\SendUDP;
use Midi\Parser\ParseReplayed;
use DiPlugin\Parser\ParseApollo;
use Twig_Loader_Filesystem;

/**
 * Add UPD logic for didi apollo.
 *
 * Set at config.yml:
 *
 * php:
 *     reporter: DiPlugin\Reporter\Reporter
 */
class Reporter extends BaseReporter
{
    const APOLLO_NAV_TABS = [
        'name' => 'Apollos',
        'href' => 'session-nav-apollo',
        'template' => 'replayed-tab-apollo.twig',
    ];

    /**
     * For CI framework code coverage
     *
     * @var string
     */
    static $coverageCIAppendCode = '
spl_autoload_register(function ($class) {
    $white = [
        "CI_Logic" => ["Logic", "core",],
        "CI_Model" => ["Model", "core",],
        "CI_Service" => ["Service", "core",],
    ];
    if (isset($white[$class])) {
        load_class(...$white[$class]);
    }
});';

    public function __construct(
        Midi $midi,
        Twig_Loader_Filesystem $twigLoader = null,
        ParseReplayedInterface $parser = null
    ) {
        parent::__construct($midi, $twigLoader, $parser);

        // for apollo
        $templateDir = __DIR__ . DR . 'Template';
        $this->twigLoader->addPath($templateDir);
        array_push($this->navTabLayouts, self::APOLLO_NAV_TABS);
        $this->parser->setSendUPDParser(self::getSendUDPParser());
    }

    public static function getSendUDPParser()
    {
        return function ($actions, $actionIndex, $parseType, &$rows) {
            $action = $actions[$actionIndex];
            $sendUDP = new SendUDP($action);
            if (ParseApollo::match($sendUDP)) {
                $request = base64_decode($sendUDP->getContent());
                $apollo = ParseApollo::parse($request);
                if ($apollo && $apollo['type'] === ParseApollo::TOGGLE_METRICS) {
                    return [
                        'Type' => ParseReplayed::ACTION_UDP,
                        'Protocol' => 'Apollo',
                        'Request' => $request,
                        'Toggle' => $apollo['toggle'],
                        'Allow' => $apollo['allow'] ? 'TRUE' : 'FALSE',
                    ];
                }
            }

            return null;
        };
    }

    public function format($data)
    {
        $data = parent::format($data);
        $data['Apollos'] = self::formatApollos($data['SendUDPs']);

        return $data;
    }

    /**
     * Unique apollos
     *
     * @param $apollos
     * @return array
     */
    public static function formatApollos($apollos)
    {
        if (empty($apollos)) {
            return $apollos;
        }

        $ret = [];
        foreach ($apollos as $apollo) {
            if (isset($ret[$apollo['Toggle']])) {
                continue;
            }
            $ret[$apollo['Toggle']] = $apollo;
        }

        return array_values($ret);
    }
}