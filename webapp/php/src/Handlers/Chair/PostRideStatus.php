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

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM rides WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$rideId]);
            $ride = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ride) {
                $this->db->rollBack();
                return (new ErrorResponse())->write(
                    $response,
                    StatusCodeInterface::STATUS_NOT_FOUND,
                    new Exception('ride not found')
                );
            }

            if ($ride['chair_id'] !== $chair->id) {
                $this->db->rollBack();
                return (new ErrorResponse())->write(
                    $response,
                    StatusCodeInterface::STATUS_BAD_REQUEST,
                    new HttpBadRequestException(
                        request: $request,
                        message: 'not assigned to this ride'
                    )
                );
            }
            switch ($req->getStatus()) {
                    // Acknowledge the ride
                case 'ENROUTE':
                    $stmt = $this->db->prepare(
                        'INSERT INTO ride_statuses (id, ride_id, status) VALUES (?, ?, ?)'
                    );
                    $stmt->execute([new Ulid(), $ride['id'], 'ENROUTE']);
                    $stmt = $this->db->prepare('UPDATE rides set status = ? WHERE id = ?');
                    $stmt->execute(['ENROUTE', $rideId]);
                    break;
                    // After Picking up user
                case 'CARRYING':
                    $status = $ride['status'];
                    if ($status !== 'PICKUP') {
                        $this->db->rollBack();
                        return (new ErrorResponse())->write(
                            $response,
                            StatusCodeInterface::STATUS_BAD_REQUEST,
                            new HttpBadRequestException(
                                request: $request,
                                message: 'chair has not arrived yet'
                            )
                        );
                    }
                    $stmt = $this->db->prepare(
                        'INSERT INTO ride_statuses (id, ride_id, status) VALUES (?, ?, ?)'
                    );
                    $stmt->execute([new Ulid(), $ride['id'], 'CARRYING']);
                    $stmt = $this->db->prepare('UPDATE rides set status = ? WHERE id = ?');
                    $stmt->execute(['CARRYING', $rideId]);
                    break;
                default:
                    $this->db->rollBack();
                    return (new ErrorResponse())->write(
                        $response,
                        StatusCodeInterface::STATUS_BAD_REQUEST,
                        new HttpBadRequestException(
                            request: $request,
                            message: 'invalid status'
                        )
                    );
            }
            $this->db->commit();
            return $this->writeNoContent($response);
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
