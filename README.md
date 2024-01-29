# Stock Sentinel 2.0

## Devlog

- Building Scraper for EFD
    - Perform intial handshake
    - Perform agreement request
    - Log csrfmiddlewaretoken
    - Perform data request
    - Perform secondary data request with X-csrf token from first
    - Run through response yielding 100 results per iteration

Right now, the scraper is not returning the right amount of data per fetch. I need to look into why.