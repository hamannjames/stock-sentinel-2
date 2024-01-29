# Stock Sentinel 2.0

This is a continuation of Stock Sentinel V1. You can check that out here: [Stock Sentinel V1](https://github.com/hamannjames/stock-sentinel)

## Devlog

- Building Scraper for EFD
    - Perform intial handshake
    - Perform agreement request
    - Log csrfmiddlewaretoken
    - Perform data request
    - Perform secondary data request with X-csrf token from first
    - Run through response yielding 100 results per iteration

Right now, the scraper is not returning the right amount of data per fetch. I need to look into why.