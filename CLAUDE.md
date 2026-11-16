# CLAUDE.md

Execution Service — Fase 4 microservice of Workshop OS (Laravel 13, PHP 8.4, MongoDB).

## Scope

- Owns the shop-floor queue: diagnosis and repair tasks, one document per service order with an embedded timeline.
- Never reads another service's database. Orders arrive in saga commands (`cmd.execution.*`).
- Architecture decisions live in `15SOAT-Fase1/docs/architecture` (RFC-004, ADR-010..015; MongoDB: ADR-012).

## Rules

- Every new function gets a test; every route gets `#[OA\*]` + a feature test.
- Routes sit behind Traefik ForwardAuth; identity comes from `X-User-*` (`TrustGatewayIdentity`).
- MongoDB must run as a replica set (transactions). Tests need a real MongoDB (`MONGODB_URI`).
- Host PHP lacks `ext-mongodb`: run artisan/tests in a container (see Commands).
- Messaging lib is synced from `15SOAT-Fase1/scripts/sync-messaging-lib.sh --no-eloquent` — edit it there.

## Commands

```bash
docker run -d --name mongo-test -p 27018:27017 mongo:7 --replSet rs0 --bind_ip_all
docker exec mongo-test mongosh --eval 'rs.initiate({_id:"rs0",members:[{_id:0,host:"127.0.0.1:27017"}]})'
docker run --rm --network host -v "$PWD":/app -w /app \
  -e MONGODB_URI='mongodb://127.0.0.1:27018/?replicaSet=rs0&directConnection=true' \
  <php-8.4-image-with-mongodb-sockets-pcov> php artisan test --coverage
EXECUTION_QUEUE_CAPACITY=0   # demo: refuse new work → saga compensates
```
