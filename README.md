# Mileo

Mileo est une application de calculs d'indémnités kilométriques simple et rapide. Mileo est l'application qui vous permet de gérer vos frais kilométriques en quelques minutes.

## Sommaire
- [Pré-requis](#pré-requis)
- [Stack de développement](#stack-de-développement)
- [Installation](#installation)
  - [Modifier la directive `DocumentRoot` d'Apache si nécessaire](#modifier-la-directive-documentroot-dapache-si-nécessaire)
  - [Installation des dépendances](#installation-des-dépendances)
  - [Variables d'environnement](#variables-denvironnement)
  - [Migration de la base de données](#migration-de-la-base-de-données)


## Pré-requis

- Symfony CLI
- Composer
- PHP 8.2+
- Apache

## Stack de développement

- Symfony 6.4
- PHP 8.2
- EasyAdminBundle 4
- JQuery
- CSS natif
- WebpackEncore

## Installation

### Modifier la directive `DocumentRoot` d'Apache si nécessaire

Tout d'abord **modifiez la directive** `DocumentRoot` d'Apache. Le chemin doit pointer sur le dossier `public` du projet afin qu'il puisse lire le fichier d'entrée `index.php`.

### Installation des dépendances

Ensuite, installez les dépendances à l'aide de Composer en lançant la commande suivante :

```bash
composer install
```
Attention: laisser les demandes de recipes par défaut

```bash
npm install && npm run build
```

### Variables d'environnement

Par la suite, vous allez devoir définir les variables d'environnement du projet.

Créez une fichier `.env.local` et définissez les variables suivantes :

```ini
APP_ENV=dev
# Voir : https://symfony.com/doc/current/doctrine.html
DATABASE_URL="mysql://db_user:your_password@127.0.0.1:3306/db_name"
```

D'autres variables optionnelles sont présentes dans le fichier `.env`. 
Ces variables correspondent en majorité à des clés API qui doivent être définies pour que l'application fonctionne même si leurs valeurs peuvent être n'importe quoi.

Si vous souhaitez tester l'application avec vos propres clés API, copiez ces variables dans votre fichier `.env.local` et réassignez leurs valeurs.

### Migration de la base de données

Une fois tout cela fait, vous devriez pouvoir définir la structure de la base de données. Pour cela, utilisez la commande : 

```bash
php bin/console doctrine:schema:update --force  

# ou

php bin/console d:s:u --force  
```
