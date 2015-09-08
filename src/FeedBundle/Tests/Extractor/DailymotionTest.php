<?php

namespace Api43\FeedBundle\Tests\Extractor;

use Api43\FeedBundle\Extractor\Dailymotion;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;

class DailymotionTest extends \PHPUnit_Framework_TestCase
{
    public function dataMatch()
    {
        return array(
            array('http://dai.ly/xockol', true),
            array('http://www.dailymotion.com/video/xockol_planete-des-hommes-partie-1-2_travel', true),
            array('https://www.dailymotion.com/video/xockol_planete-des-hommes-partie-1-2_travel', true),
            array('http://dailymotion.com/video/xockol_planete-des-hommes-partie-1-2_travel', true),
            array('https://goog.co', false),
            array('http://user@:80', false),
        );
    }

    /**
     * @dataProvider dataMatch
     */
    public function testMatch($url, $expected)
    {
        $dailymotion = new Dailymotion();
        $this->assertEquals($expected, $dailymotion->match($url));
    }

    public function testContent()
    {
        $client = new Client();

        $mock = new Mock([
            new Response(200, [], Stream::factory(json_encode(array('title' => 'my title', 'thumbnail_url' => 'http://0.0.0.0/img.jpg', 'html' => '<iframe/>')))),
            new Response(200, [], Stream::factory('')),
            new Response(400, [], Stream::factory('oops')),
        ]);

        $client->getEmitter()->attach($mock);

        $dailymotion = new Dailymotion();
        $dailymotion->setClient($client);

        $logHandler = new TestHandler();
        $logger = new Logger('test', array($logHandler));
        $dailymotion->setLogger($logger);

        // first test fail because we didn't match an url, so DailymotionUrl isn't defined
        $this->assertEmpty($dailymotion->getContent());

        $dailymotion->match('https://www.dailymotion.com/video/xockol_planete-des-hommes-partie-1-2_travel');

        // consecutive calls
        $this->assertEquals('<div><h2>my title</h2><p><img src="http://0.0.0.0/img.jpg"></p><iframe/></div>', $dailymotion->getContent());
        // this one will got an empty array
        $this->assertEmpty($dailymotion->getContent());
        // this one will catch an exception
        $this->assertEmpty($dailymotion->getContent());

        $this->assertTrue($logHandler->hasWarning('Dailymotion extract failed for: https://www.dailymotion.com/video/xockol_planete-des-hommes-partie-1-2_travel'), 'Warning message matched');
    }
}
