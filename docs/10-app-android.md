# App Android

Carpeta: `mobile/`

Misma API que Electron. Escaneo por **cámara** y **lector USB/BT**.

## Compilar APK

Requisitos: Node 20+, JDK 17, Android SDK (`ANDROID_HOME`).

```bash
cd mobile
npm install
npx cap sync android
npm run build:apk
```

APK: `mobile/dist/POSMoon-1.0.0-debug.apk`

Instalar:

```bash
adb install mobile/dist/POSMoon-1.0.0-debug.apk
```

CI: workflow `.github/workflows/android-apk.yml` genera APK en GitHub Actions.

## Uso en el teléfono

1. Instalar APK
2. Activar con URL `https://cmoon.aiporvos.com`
3. Aceptar permiso de **cámara**

### Escaneo

| Botón | Función |
|-------|---------|
| **Escanear (cámara)** | Modo continuo — varios productos — **Terminar escaneo** |
| **1 código** | Escáner nativo Google, un producto |
| Campo búsqueda | Lector USB/BT (modo teclado) |

### Reset

Ajustes → Aplicaciones → POSMoon → Borrar almacenamiento

## Códigos soportados

- EAN producto (`7790895000123`, etc.)
- `cantidad*codigo`
- Balanza: `2` + PLU + gramos (productos pesables PLU `00101`–`00103` en seeder)

## Notas

- Escaneo por cámara **solo en APK**, no en navegador
- Tras cambios de catálogo en servidor: **Sync**
- Ventas offline se sincronizan al volver internet
