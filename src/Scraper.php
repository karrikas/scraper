<?php
namespace Scraper;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Goutte\Client;
use Stash;
use GuzzleHttp;

abstract class Scraper extends Command
{
    protected $proxy = [];

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->log = new Logger('debug');
        $this->log->pushHandler(new StreamHandler(__dir__.'/../debug.log', Logger::INFO));

        $this->loadProxies();

        if (method_exists($this, 'init')) {
            $this->init($input, $output);
        }
    }

    public function loadProxies()
    {
        $proxiFile = __dir__.'/../proxy.txt';
        if (!file_exists($proxiFile)) {
            return false;
        }
        $this->log->info('load proxies');

        $proxiesInfo = file_get_contents($proxiFile);
        $proxies = explode("\n", $proxiesInfo);
        foreach ($proxies as $proxy) {
            if (empty($proxy)) {
                continue;
            }
            list($ip, $port, $user, $pass) = explode(':', $proxy);
            $url = sprintf(
                'tcp://%s:%s@%s:%s',
                $user,
                $pass, 
                $ip, 
                $port
            );
            $url = sprintf(
                'tcp://%s:%s',
                $ip, 
                $port
            );

            $this->proxy[] = [
                'settings' => 
                [
                    'ip' => $ip,
                    'port' => $port,
                    'user' => $user,
                    'pass' => $pass
                ]
            ];
        }
    }

    protected function getProxy()
    {
        $hasProxy = false;

        if (count($this->proxy) == 0) {
            return false;
        }

        $this->log->info('find proxy');

        while (!$hasProxy) {
            foreach ($this->proxy as $key => $info) {
                if (!isset($info['next']) || $info['next'] < time()) {
                    $this->proxy[$key]['next'] = time() + rand(2, 5);
                    $this->log->info('use proxy', $info);
                    return $this->proxy[$key]['settings'];
                }
            }
            $this->log->info('free proxy not found');
            sleep(1);
        }

        return false;

    }

    protected function request($key, $url)
    {
        $this->log->info('new request', ['key' => $key, 'url' => $url]);

        $clientParameters = [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36',
            ],
        ];

        if ($proxy = $this->getProxy())
        {
            $clientParameters['curl'] = [
                CURLOPT_PROXY => $proxy['ip'],
                CURLOPT_PROXYPORT => $proxy['port'],
                CURLOPT_PROXYUSERPWD => $proxy['user'].':'.$proxy['pass'],
            ];
        }

        try {
            $client = new GuzzleHttp\Client(['http_errors' => false]);
            $response = $client->request('GET', $url, $clientParameters);
            $data = $response->getBody();
        } catch (\Exception $e) {
            $this->log->error('guzzle request', (array) $e);
            return false;
        }

        return (string) $data;
    }

    public function memoryLog($msg = null)
    {
        if (!is_null($msg)) {
            $this->output->writeln($msg);
        }

        $info = sprintf('%dKB/%dKB', round(memory_get_usage(true) / 1024), memory_get_peak_usage(true) / 1024);

        $this->log->info($info);
    }

    public function saveFile($url, $file)
    {
        $source = $this->request('image', $url);
        return file_put_contents($file, $source);
    }
}
