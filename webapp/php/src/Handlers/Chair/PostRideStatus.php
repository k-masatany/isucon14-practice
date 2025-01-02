<?php

declare(strict_types=1);

namespace IsuRide\Handlers\Chair;

use Fig\Http\Message\StatusCodeInterface;
use IsuRide\Database\Model\Chair;
use IsuRide\Handlers\AbstractHttpHandler;
use PDOException;
use IsuRide\Model\ChairPostRideStatusRequest;
use IsuRide\Response\ErrorResponse;
use PDO;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Uid\Ulid;

class PostRideStatus extends AbstractHttpHandler
{
    public function __construct(
        private readonly PDO $db,
    ) {}

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $rideId = $args['ride_id'];
        $chair = $request->getAttribute('chair');
        assert($chair instanceof Chair);

        $req = new ChairPostRideStatusRequest((array)$request->getParsedBody());
        if (!$req->valid()) {
            return (new ErrorResponse())->write(
                $response,
                StatusCodeInterface::STATUS_BAD_REQUEST,
                new HttpBadRequestException(
                    request: $request
                )
            );
        }

        $nextStatus = $req->getStatus();
        if ($nextStatus !== 'ENROUTE' && $nextStatus !== 'CARRYING') {
            return (new ErrorResponse())->write(
                $response,
                StatusCodeInterface::STATUS_BAD_REQUEST,
                new HttpBadRequestException(
                    request: $request,
                    message: 'invalid status'
                )
            );
        }

        $ride = null;
        if ($nextStatus === 'ENROUTE') {
            $stmt = $this->db->prepare(
                'SELECT * FROM rides WHERE id = ? AND chair_id = ?'
            );
            $stmt->execute([$rideId, $chair->id]);
            $ride = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ride) {
                return (new ErrorResponse())->write(
                    $response,
                    StatusCodeInterface::STATUS_NOT_FOUND,
                    new Exception('ride not found')
                );
            }
        } else if ($nextStatus === 'CARRYING') {
            $stmt = $this->db->prepare('SELECT * FROM rides WHERE id = ? AND chair_id = ? AND status = "PICKUP"');
            $stmt->execute([$rideId, $chair->id]);
            $ride = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ride) {
                return (new ErrorResponse())->write(
                    $response,
                    StatusCodeInterface::STATUS_BAD_REQUEST,
                    new HttpBadRequestException(
                        request: $request,
                        message: 'chair has not arrived yet'
                    )
                );
            }
        }

        if ($nextStatus === 'ENROUTE') {
            $stmt = $this->db->prepare('INSERT INTO ride_statuses (id, ride_id, status) VALUES (?, ?, ?)');
            $stmt->execute([new Ulid(), $ride['id'], 'ENROUTE']);
            $stmt = $this->db->prepare('UPDATE rides set status = ? WHERE id = ?');
            $stmt->execute(['ENROUTE', $rideId]);
        } else if ($nextStatus === 'CARRYING') {
            $stmt = $this->db->prepare('INSERT INTO ride_statuses (id, ride_id, status) VALUES (?, ?, ?)');
            $stmt->execute([new Ulid(), $ride['id'], 'CARRYING']);
            $stmt = $this->db->prepare('UPDATE rides set status = ? WHERE id = ?');
            $stmt->execute(['CARRYING', $rideId]);
        }

        return $this->writeNoContent($response);
    }
}
