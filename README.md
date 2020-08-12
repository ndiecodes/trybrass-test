# TryBrass Test

## Usage

-   `git clone https://github.com/ndiecodes/trybrass-test.git`
-   `cd trybrass-test`
-   `composer install`
-   `cp .env.example .env` ( Edit with appropriate configs)
-   `php artisan jwt:secret`
-   `php artisan migrate`
-   `php -S localhost:8000 -t public`
