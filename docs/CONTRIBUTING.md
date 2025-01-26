# Contributing

If you are interested to improve this library, follow these instructions.

## Requirements

* Docker 27+

## Steps

Clone this repo.

Create a fork from the branch `main`.

Create the file `.env` based on `.env.example`:

```
cp .env.example .env
```

Build the image:

```
docker compose build
```

Run the containers:

```
docker compose --profile dev up
```

The command above will start 3 services:

1. `lib`: it is a container free for use. You can access it using `docker compose exec lib sh` and run the comands of composer with a prepared PHP environment.
1. `test`: it is a container that will run the quality checks and tests, then it will serve the coverage report at http://localhost:8080
1. `doc`: it is a container that will generate the Doc dynamically (based on PhpDoc), then it will serve the Doc at http://localhost:8080

Ensure you are following the expected standard (PSR-12), and did not break any test.

After that, you can submit your merge request to the main repo.
