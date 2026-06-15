# Mantenimiento y troubleshooting

## Operaciones rutinarias

```bash
cd /var/www/html

# Migraciones tras deploy
php artisan migrate --force

# Cache tras cambiar .env en Dokploy
php artisan config:cache
php artisan route:cache

# Reiniciar colas tras deploy de código
php artisan queue:restart
```

## Backups MySQL

```bash
docker compose exec mysql mysqldump -u root -p"$DB_ROOT_PASSWORD" cmoon > backup-$(date +%F).sql
```

Restaurar:

```bash
docker compose exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" cmoon < backup.sql
```

## Redeploy completo (Dokploy)

1. Push a `main`
2. Redeploy en Dokploy
3. Verificar `/up`
4. `php artisan migrate --force` si hay migraciones nuevas

## Problemas frecuentes

| Síntoma | Causa | Solución |
|---------|-------|----------|
| 404 en `/api/desktop/activate` | Dominio apunta al POS viejo PHP | Usar URL del POSMoon Laravel |
| `fetch failed` en desktop | DNS incorrecto o sin internet | URL correcta; ventas se guardan local si falla red |
| Sistema suspendido | Mora Moon o CSS overlay | `MOON_COBRO_ENABLED=false` o regularizar cobro; actualizar app |
| Seeder no existe | Código no desplegado | Push + redeploy Dokploy |
| `artisan` not found | Directorio incorrecto | `cd /var/www/html` |
| Electron sandbox error Linux | Permisos chrome-sandbox | `--no-sandbox` |
| AppImage no arranca | Idem | `./AppImage --no-sandbox` |
| Cámara Android no funciona | Permiso o APK vieja | Reinstalar APK; aceptar cámara |

## Logs y diagnóstico

```bash
docker compose logs -f app
php artisan about
curl -s https://cmoon.aiporvos.com/up
curl -s -o /dev/null -w "%{http_code}" -X POST https://cmoon.aiporvos.com/api/desktop/activate
```

## Limpiar cache Laravel

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Actualizar dependencias PHP (desarrollo)

```bash
composer update
docker compose up -d --build
```
