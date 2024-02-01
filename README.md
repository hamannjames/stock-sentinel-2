# Stock Sentinel 2.0

This is a continuation of Stock Sentinel V1. You can check that out here: [Stock Sentinel V1](https://github.com/hamannjames/stock-sentinel)

## Devlog

### 1-29-24

- Building Scraper for EFD
    - Perform intial handshake
    - Perform agreement request
    - Log csrfmiddlewaretoken
    - Perform data request
    - Perform secondary data request with X-csrf token from first
    - Run through response yielding 100 results per iteration

Right now, the scraper is not returning the right amount of data per fetch. I need to look into why.

### 2-1-24

Figured out that the scraper was returning data correctly but the test was written incorrectly. Also created a wrapper singleton class around the efd connector class to use in testing for performance improvement.