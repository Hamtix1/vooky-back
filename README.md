# Proyecto Laravel 🚀

Este es un proyecto desarrollado en **Laravel**.  
A continuación encontrarás los pasos para instalarlo y ejecutarlo en tu entorno local.

## ⚙️ Instalación

1. **Clonar el repositorio**
```bash
git clone https://github.com/Hamtix1/vooky-back.git

```
Ingresar a la carpeta del proyecto
**Instalar dependencias de PHP**
```bash
composer install
```


**Instalar dependencias de JavaScript**
```bash
npm install
```

**Configurar el archivo .env**

Copiar el archivo de ejemplo:

cp .env.example .env


**Configurar las variables de entorno (base de datos, mail, etc.)**

**Generar la key de la aplicación**
```bash
php artisan key:generate


Ejecutar migraciones y seeders

php artisan migrate --seed


Compilar los assets

npm run dev
```