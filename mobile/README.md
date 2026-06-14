# CMoon POS — Android

App móvil offline con la misma API que la caja de escritorio (`/api/desktop/*`).

## Funciones

- Activación con licencia Moon
- Catálogo local (productos, medios de pago, clientes)
- Ventas offline en cola → sync al volver internet
- **Lector USB/Bluetooth** (modo teclado): enfocar el campo de búsqueda y escanear
- **Cámara**: botón **Escanear** → varios productos → **Terminar escaneo** → **Cobrar**
- Códigos: `cantidad*codigo`, balanza EAN-13 (`2` + PLU + gramos)

## Requisitos para compilar APK

- Node.js 20+
- **Android Studio** o Android SDK + JDK 17
- Variables: `ANDROID_HOME` apuntando al SDK

## Instalar y compilar

```bash
cd mobile
npm install
npx cap add android    # solo la primera vez
npx cap sync android
npm run build:apk
```

APK debug: `android/app/build/outputs/apk/debug/app-debug.apk`

Instalar en el teléfono:

```bash
adb install android/app/build/outputs/apk/debug/app-debug.apk
```

## Probar en navegador (sin cámara nativa)

Sirve `www/` con cualquier servidor estático; el escaneo por cámara solo funciona en la APK.

## Activación

1. URL: `https://cmoon.aiporvos.com`
2. Usuario/contraseña POS
3. ID cliente Moon
4. Nombre de la caja

## Reset (volver a activar)

En la app: borrar datos de la app en Ajustes → Aplicaciones → CMoon POS → Borrar almacenamiento.
