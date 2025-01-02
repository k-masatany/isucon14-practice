<?php

declare(strict_types=1);

namespace IsuRide\Handlers\Internal;

use IsuRide\Handlers\AbstractHttpHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

// このAPIをインスタンス内から一定間隔で叩かせることで、椅子とライドをマッチングさせる
class GetMatching extends AbstractHttpHandler
{
    public function __construct(
        private readonly PDO $db,
    ) {}

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        // rides 取得
        $stmt = $this->db->prepare('SELECT * FROM rides WHERE chair_id IS NULL ORDER BY created_at LIMIT 10');
        $stmt->execute();
        $rides = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rides) == 0) {
            return $this->writeNoContent($response);
        }

        foreach ($rides as $ride) {
            // chairs 取得
            $stmt = $this->db->prepare('
                SELECT
                    chairs.id, 
                    ((ABS(lcl.last_latitude - ?) + ABS(lcl.last_longitude - ?)) / cm.speed) + ((ABS(? - ?) + ABS(? - ?)) / cm.speed) AS manhattan FROM chairs 
                    INNER JOIN last_chair_locations AS lcl ON lcl.chair_id = chairs.id 
                    INNER JOIN chair_models as cm ON cm.name = chairs.model
                    LEFT JOIN rides as r ON r.chair_id = chairs.id
                    LEFT JOIN complete_rides as cr ON cr.ride_id = r.id
                    WHERE chairs.is_active = TRUE
                    ORDER BY manhattan ASC
                    LIMIT 1
            ');
            $stmt->execute([$ride['pickup_latitude'], $ride['pickup_longitude'], $ride['destination_latitude'], $ride['pickup_latitude'], $ride['destination_longitude'], $ride['pickup_longitude']]);
            $chair = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$chair) {
                continue;
            }

            $stmt = $this->db->prepare(
                'SELECT COUNT(*) = 0 FROM (SELECT COUNT(chair_sent_at) = 6 AS completed FROM ride_statuses WHERE ride_id IN (SELECT id FROM rides WHERE chair_id = ?) GROUP BY ride_id) is_completed WHERE completed = FALSE'
            );
            $stmt->execute([$chair['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $empty = $result['COUNT(*) = 0'];
            if ($empty) {
                $stmt = $this->db->prepare('UPDATE rides SET chair_id = ? WHERE id = ?');
                $stmt->execute([$chair['id'], $ride['id']]);
                // $stmt = $this->db->prepare('UPDATE chairs SET is_active = FALSE WHERE id = ?');
                // $stmt->execute([$chair['id']]);
            }
            // $stmt = $this->db->prepare('SELECT * FROM complete_rides INNER JOIN rides ON rides.id = complete_rides.ride_id INNER JOIN chairs ON chairs.id = rides.chair_id WHERE chairs.id = ?');
            // $stmt->execute([$chair['id']]);
            // $completed = $stmt->fetch(PDO::FETCH_ASSOC);
            // if ($completed && $completed['completed'] == FALSE) {
            //     continue;
            // }
        }

        return $this->writeNoContent($response);
    }
}
