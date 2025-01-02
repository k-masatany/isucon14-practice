<?php

declare(strict_types=1);

namespace IsuRide\Handlers\App;

use Fig\Http\Message\StatusCodeInterface;
use IsuRide\Database\Model\Chair;
use IsuRide\Database\Model\ChairLocation;
use IsuRide\Database\Model\RetrievedAt;
use IsuRide\Handlers\AbstractHttpHandler;
use IsuRide\Model\AppGetNearbyChairs200Response;
use IsuRide\Model\AppGetNearbyChairs200ResponseChairsInner;
use IsuRide\Model\Coordinate;
use IsuRide\Response\ErrorResponse;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetNearbyChairs extends AbstractHttpHandler
{
    public function __construct(
        private readonly PDO $db,
    ) {}

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args
     * @return ResponseInterface
     * @throws \Exception
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $queryParams = $request->getQueryParams();
        $latStr = $queryParams['latitude'] ?? '';
        $lonStr = $queryParams['longitude'] ?? '';
        $distanceStr = $queryParams['distance'] ?? '';
        if ($latStr === '' || $lonStr === '') {
            return (new ErrorResponse())->write(
                $response,
                StatusCodeInterface::STATUS_BAD_REQUEST,
                new \Exception('latitude or longitude is empty')
            );
        }
        if (!is_numeric($latStr)) {
            return (new ErrorResponse())->write(
                $response,
                StatusCodeInterface::STATUS_BAD_REQUEST,
                new \Exception('latitude is invalid')
            );
        }
        $lat = (int)$latStr;
        if (!is_numeric($lonStr)) {
            return (new ErrorResponse())->write(
                $response,
                StatusCodeInterface::STATUS_BAD_REQUEST,
                new \Exception('longitude is invalid')
            );
        }
        $lon = (int)$lonStr;
        $distance = 50;
        if ($distanceStr !== '') {
            if (!is_numeric($distanceStr)) {
                return (new ErrorResponse())->write(
                    $response,
                    StatusCodeInterface::STATUS_BAD_REQUEST,
                    new \Exception('distance is invalid')
                );
            }
            $distance = (int)$distanceStr;
        }
        $coordinate = new Coordinate([
            'latitude' => $lat,
            'longitude' => $lon,
        ]);
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('SELECT chairs.*, lcl.last_id, lcl.last_latitude, lcl.last_longitude, lcl.last_created_at  FROM chairs INNER JOIN last_chair_locations AS lcl ON lcl.chair_id = chairs.id LEFT JOIN rides ON rides.chair_id = chairs.id WHERE is_active = TRUE AND (rides.id IS NULL OR rides.status = "COMPLETED")');
            $stmt->execute();
            $chairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $nearbyChairs = [];
            foreach ($chairs as $dbChair) {
                $chair = new Chair(
                    id: $dbChair['id'],
                    ownerId: $dbChair['owner_id'],
                    name: $dbChair['name'],
                    accessToken: $dbChair['access_token'],
                    model: $dbChair['model'],
                    isActive: (bool)$dbChair['is_active'],
                    createdAt: $dbChair['created_at'],
                    updatedAt: $dbChair['updated_at']
                );
                $chairLocation = new ChairLocation(
                    id: $dbChair['last_id'],
                    chairId: $dbChair['id'],
                    latitude: (int)$dbChair['last_latitude'],
                    longitude: (int)$dbChair['last_longitude'],
                    createdAt: $dbChair['last_created_at']
                );
                $distanceToChair = $this->calculateDistance(
                    $coordinate->getLatitude(),
                    $coordinate->getLongitude(),
                    $chairLocation->latitude,
                    $chairLocation->longitude
                );
                if ($distanceToChair <= $distance) {
                    $nearbyChairs[] = new AppGetNearbyChairs200ResponseChairsInner([
                        'id' => $chair->id,
                        'name' => $chair->name,
                        'model' => $chair->model,
                        'current_coordinate' => new Coordinate([
                            'latitude' => $chairLocation->latitude,
                            'longitude' => $chairLocation->longitude,
                        ]),
                    ]);
                }
            }
            $stmt = $this->db->prepare('SELECT CURRENT_TIMESTAMP(6) AS ct');
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $retrievedAt = new RetrievedAt($row['ct']);
            $this->db->commit();
            return $this->writeJson($response, new AppGetNearbyChairs200Response([
                'chairs' => $nearbyChairs,
                'retrieved_at' => $retrievedAt->unixMilliseconds(),
            ]));
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return (new ErrorResponse())->write(
                $response,
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }
}
