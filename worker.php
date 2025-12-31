<?php

require __DIR__ . '/vendor/autoload.php';

use Predis\Client as RedisClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Config Redis
 */
$redis = new RedisClient([
    'scheme'   => 'tcp',
    'host'     => getenv('REDIS_HOST'),
    'port'     => getenv('REDIS_PORT'),
    'password' => getenv('REDIS_PASSWORD'),
]);

echo "[Worker] Catavento Rastreio iniciado...\n";

$queueName = 'catavento-rastreio';

while (true) {
    try {
        /**
         * BLPOP bloqueante
         */
        $data = $redis->blpop([$queueName], 0);

        if (!$data || !isset($data[1])) {
            continue;
        }

        $payload = json_decode($data[1], true);

        if (!$payload) {
            echo "[ERRO] Payload inválido\n";
            continue;
        }

        $idEmpresa  = $payload['idEmpresa'] ?? null;
        $codPedido  = $payload['CodPedido'] ?? null;

        if (!$idEmpresa || !$codPedido) {
            echo "[ERRO] idEmpresa ou CodPedido ausente\n";
            continue;
        }

        /**
         * Buscar token da Catavento no Redis
         * key: tokens:catavento:{idEmpresa}
         */
        $tokenKey = "tokens:catavento:{$idEmpresa}";
        $tokenCatavento = $redis->get($tokenKey);

        if (!$tokenCatavento) {
            throw new Exception("Token da Catavento não encontrado para empresa {$idEmpresa}");
        }

        /**
         * Consultar API Catavento
         */
        $client = new Client([
            'base_uri' => 'https://api.cataventobr.com.br',
            'verify'   => false,
            'timeout'  => 30,
        ]);

        $response = $client->request(
            'GET',
            "/BDIApi/Pedido/Situacao",
            [
                'query' => [
                    'codigo' => $codPedido
                ],
                'headers' => [
                    'API_TOKEN' => $tokenCatavento
                ]
            ]
        );

        $body = (string) $response->getBody();

        /**
         * Log do retorno
         */
        echo "[SUCESSO] Empresa {$idEmpresa} | Pedido {$codPedido}\n";
        echo "[RETORNO CATAVENTO]\n";
        echo $body . "\n";
    } catch (RequestException $e) {

        if ($e->hasResponse()) {
            $errorBody = (string) $e->getResponse()->getBody();
            echo "[ERRO API CATAVENTO] {$errorBody}\n";
        } else {
            echo "[ERRO REQUEST] " . $e->getMessage() . "\n";
        }
    } catch (Exception $e) {
        echo "[EXCEPTION] " . $e->getMessage() . "\n";
    }
}