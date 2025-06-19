# Logging

Each test run is logged under `microunit/bin/run_logs`. This folder will contain one log file for every time you run your tests. By default this folder will only contain one file which is the log from your latest run.

If you want to keep all run logs see [Configuration: persistRunLogs](configuration.md#persistrunlogs) for details.

---

## Upcoming Features

Even tho currently not supported by MicroUnit below is a list of features coming soon in regards to logging.

### Configuring a custom logs folder

Adds the ability to Configure a custom logs folder where the run logs are going to be stored.

### Configurable log level

Currently the log level is fixed to `E_ALL`, however it is planned to provide a way to customize this log level depending on the level of detail you want.
