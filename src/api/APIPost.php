<?php
/* Copyright (C) 2011 by iRail vzw/asbl
 * © 2015 by Open Knowledge Belgium vzw/asbl
 *
 * This class foresees in basic HTTP functionality. It will get all the POST vars and put it in a request.
 *
 * @author Stan Callewaert
 */

namespace Irail\api;

use Dotenv\Dotenv;
use Exception;
use Irail\api\occupancy\OccupancyDao;
use Irail\api\occupancy\OccupancyOperations;
use Irail\api\output\Json;
use Irail\api\output\Xml;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;


class APIPost
{
    const SUPPORTED_FILE_FORMATS = ['Json', 'Jsonp', 'Xml'];
    private $postData;
    private $resourcename;
    private $log;
    private $method;
    private $mongodb_url;
    private $mongodb_db;

    public function __construct($resourcename, $postData, $method)
    {
        //Default timezone is Brussels
        date_default_timezone_set('Europe/Brussels');

        $this->resourcename = $resourcename;
        $this->postData = json_decode($postData);
        $this->method = $method;

        $dotenv = new Dotenv(dirname(__DIR__, 2));
        $dotenv->load();
        $this->mongodb_url = getenv('MONGODB_URL');
        $this->mongodb_db = getenv('MONGODB_DB');

        try {
            $this->log = new Logger('irapi');
            //Create a formatter for the logs
            $logFormatter = new LineFormatter("%context%\n", 'Y-m-d\TH:i:s');
            $streamHandler = new StreamHandler(__DIR__ . '/../../storage/irapi.log', Logger::INFO);
            $streamHandler->setFormatter($logFormatter);
            $this->log->pushHandler($streamHandler);
        } catch (Exception $e) {
            $this->buildError($e);
        }
    }

    public function writeToMongo($ip)
    {
        if ($this->method == "POST") {
            try {
                if ($this->resourcename == 'occupancy') {
                    $this->occupancyToMongo($ip);
                }
            } catch (Exception $e) {
                $this->buildError($e);
            }
        } else {
            $this->buildError(new Exception('Only post requests are allowed', 405));
        }
    }

    /*
     * Required:
     * - connection
     * - occupancy
     * Optional:
     * - to
     *
     * Optional TODO: Test if the connection URI exists.
     */
    private function occupancyToMongo()
    {
        if (!is_null($this->postData->connection) && !is_null($this->postData->from) && !is_null($this->postData->date) && !is_null($this->postData->vehicle) && !is_null($this->postData->occupancy)) {
            if (OccupancyOperations::isCorrectPostURI($this->postData->occupancy)) {
                try {
                    if (!in_array(
                        $this->postData->occupancy,
                        [OccupancyOperations::LOW, OccupancyOperations::MEDIUM, OccupancyOperations::HIGH]
                    )
                    ) {
                        header('HTTP/1.1 400 Invalid Request');
                        die();
                    }

                    // validate connection ids.
                    if (preg_match(
                        "/^http:\/\/irail\.be\/connections\/\d{7}\/\d{8}\/.{4,7}$/",
                        $this->postData->connection
                    ) === 0
                    ) {
                        header('HTTP/1.1 400 Invalid Connection ID');
                        die();
                    }

                    // validate station id (should be an irail identifier).
                    if (preg_match("/^http:\/\/irail\.be\/stations\//", $this->postData->from) === 0
                    ) {
                        header('HTTP/1.1 400 Invalid station ID');
                        die();
                    }

                    // validate vehicle id (should be an irail identifier). http://irail.be/IC817
                    if (preg_match("/^http:\/\/irail\.be\/vehicle\/[\w\d]+$/", $this->postData->vehicle) === 0
                    ) {
                        header('HTTP/1.1 400 Invalid vehicle ID');
                        die();
                    }

                    // validate date.
                    if (preg_match("/^\d{8}$/", $this->postData->date) === 0
                    ) {
                        header('HTTP/1.1 400 Invalid date');
                        die();
                    }

                    if (substr($this->postData->date, 4, 2) > 12) {
                        header('HTTP/1.1 400 Invalid date (month > 12)');
                        die();
                    }

                    if (substr($this->postData->date, 6, 2) > 31) {
                        header('HTTP/1.1 400 Invalid date (day > 31)');
                        die();
                    }

                    // Return a 201 message and redirect the user to the iRail api GET page of a vehicle

                    header('HTTP/1.1 201 Created');
                    header('Access-Control-Allow-Origin: *');
                    header('Access-Control-Request-Method: POST, OPTIONS');
                    header('Access-Control-Request-Headers: Content-Type');
                    header('Access-Control-Allow-Headers: Content-Type');
                    header('Location: https://api.irail.be/vehicle/?id=BE.NMBS.' . basename($this->postData->vehicle));

                    $postInfo = [
                        'connection' => $this->postData->connection,
                        'from' => $this->postData->from,
                        'date' => $this->postData->date,
                        'vehicle' => $this->postData->vehicle,
                        'occupancy' => $this->postData->occupancy,
                    ];

                    // Add optional to parameters
                    if (isset($this->postData->to)) {
                        $postInfo['to'] = $this->postData->to;
                    }

                    // Log the post in the iRail log file
                    $this->writeLog($postInfo);

                    OccupancyDao::processFeedback($postInfo);
                } catch (Exception $e) {
                    $this->buildError($e);
                }
            } else {
                throw new Exception(
                    'Make sure that the occupancy parameter is one of these URIs: https://api.irail.be/terms/low, https://api.irail.be/terms/medium or https://api.irail.be/terms/high',
                    400
                );
            }
        } else {
            throw new Exception(
                'Incorrect post parameters, the occupancy post request must contain the following parameters: connection, from, date, vehicle and occupancy (optionally "to" can be given as a parameter).',
                400
            );
        }
    }

    /**
     * @param $e
     */
    private function buildError($e)
    {
        $this->logError($e);
        //Build a nice output
        $format = '';
        if (isset($_GET['format'])) {
            $format = $_GET['format'];
        }
        if ($format == '') {
            $format = 'Xml';
        }
        $format = ucfirst(strtolower($format));
        if (isset($_GET['callback']) && $format == 'Json') {
            $format = 'Jsonp';
        }
        if (!in_array($format, self::SUPPORTED_FILE_FORMATS)) {
            $format = 'Xml';
        }
        if ($format == 'Json') {
            $printer = new Json(null);
        } else {
            $printer = new Xml(null);
        }
        $printer->printError($e->getCode(), $e->getMessage());
        exit(0);
    }

    /**
     * @param Exception $e
     */
    private function logError(Exception $e)
    {
        if ($e->getCode() >= 500) {
            $this->log->addCritical($this->resourcename, [
                "querytype" => $this->resourcename,
                "error" => $e->getMessage(),
                "code" => $e->getCode(),
                "query" => $this->postData
            ]);
        } else {
            $this->log->addError($this->resourcename, [
                "querytype" => $this->resourcename,
                "error" => $e->getMessage(),
                "code" => $e->getCode(),
                "query" => $this->postData
            ]);
        }
    }

    /**
     * Writes an entry to the log in level "INFO"
     */
    private function writeLog($postInfo)
    {
        $this->log->addInfo($this->resourcename, [
            'querytype' => $this->resourcename,
            'querytime' => date('c'),
            'post' => $postInfo,
            'user_agent' => $this->maskEmailAddress($_SERVER['HTTP_USER_AGENT']),
        ]);
    }

    /**
     * Obfuscate an email address in a user agent. abcd@defg.be becomes a***@d***.be.
     *
     * @param $userAgent
     * @return string
     */
    private function maskEmailAddress($userAgent): string
    {
        // Extract information
        $hasMatch = preg_match("/([^\(\) @]+)@([^\(\) @]+)\.(\w{2,})/", $userAgent, $matches);
        if (!$hasMatch) {
            // No mail address in this user agent.
            return $userAgent;
        }
        $mailReceiver = substr($matches[1], 0, 1) . str_repeat('*', strlen($matches[1]) - 1);
        $mailDomain = substr($matches[2], 0, 1) . str_repeat('*', strlen($matches[2]) - 1);

        $obfuscatedAddress = $mailReceiver . '@' . $mailDomain . '.' . $matches[3];

        $userAgent = preg_replace("/([^\(\) ]+)@([^\(\) ]+)\.(\w{2,})/", $obfuscatedAddress, $userAgent);
        return $userAgent;
    }
}
