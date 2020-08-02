<?php

namespace Tests\integration;

use GuzzleHttp\Client;

class LiveboardIntegrationTest extends IntegrationTestCase
{
    public function test_xml_missingParameters_shouldReturn400()
    {
        $client = new Client(['http_errors' => false]);

        $response = $client->request("GET", "http://localhost:8080/liveboard.php");
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals("application/xml;charset=UTF-8", $response->getHeader("content-type")[0]);
    }

    public function test_xml_invalidParameters_shouldReturn404()
    {
        $client = new Client(['http_errors' => false]);

        $response = $client->request("GET", "http://localhost:8080/liveboard.php?id=1234");
        self::assertEquals(404, $response->getStatusCode());
        self::assertEquals("application/xml;charset=UTF-8", $response->getHeader("content-type")[0]);

        $response = $client->request("GET", "http://localhost:8080/liveboard.php?station=fake");
        self::assertEquals(404, $response->getStatusCode());
        self::assertEquals("application/xml;charset=UTF-8", $response->getHeader("content-type")[0]);
    }

    public function test_xml_validParameters_shouldReturn200()
    {
        $client = new Client(['http_errors' => false]);

        $response = $client->request("GET", "http://localhost:8080/liveboard.php?id=008844503");
        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals("application/xml;charset=UTF-8", $response->getHeader("content-type")[0]);

        $response = $client->request("GET", "http://localhost:8080/liveboard.php?station=Welkenraedt");
        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals("application/xml;charset=UTF-8", $response->getHeader("content-type")[0]);
    }

    public function test_json_missingParameters_shouldReturn400()
    {
        $client = new Client(['http_errors' => false]);

        $response = $client->request("GET", "http://localhost:8080/liveboard.php?format=json");
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals("application/json;charset=UTF-8", $response->getHeader("content-type")[0]);
    }

    public function test_json_invalidParameters_shouldReturn404()
    {
        $client = new Client(['http_errors' => false]);

        $response = $client->request("GET", "http://localhost:8080/liveboard.php?format=json&id=1234");
        self::assertEquals(404, $response->getStatusCode());
        self::assertEquals("application/json;charset=UTF-8", $response->getHeader("content-type")[0]);

        $response = $client->request("GET", "http://localhost:8080/liveboard.php?format=json&station=fake");
        self::assertEquals(404, $response->getStatusCode());
        self::assertEquals("application/json;charset=UTF-8", $response->getHeader("content-type")[0]);
    }

    public function test_json_validParameters_shouldReturn200()
    {
        $client = new Client(['http_errors' => false]);

        $response = $client->request("GET", "http://localhost:8080/liveboard.php?format=json&id=008844503");
        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals("application/json;charset=UTF-8", $response->getHeader("content-type")[0]);

        $response = $client->request("GET", "http://localhost:8080/liveboard.php?format=json&station=Welkenraedt");
        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals("application/json;charset=UTF-8", $response->getHeader("content-type")[0]);
    }
}
