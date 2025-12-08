FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates && rm -rf /var/lib/apt/lists/*

# supercronic для cron в stdout
ARG SUPERCRONIC_VERSION=0.2.1
RUN curl -fsSL -o /usr/local/bin/supercronic \
    https://github.com/aptible/supercronic/releases/download/v${SUPERCRONIC_VERSION}/supercronic-linux-amd64 \
    || curl -fsSL -o /usr/local/bin/supercronic \
        https://github.com/aptible/supercronic/releases/latest/download/supercronic-linux-amd64
RUN chmod +x /usr/local/bin/supercronic

WORKDIR /opt/telemetry
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY main.py .
COPY entrypoint.sh /usr/local/bin/telemetry_entrypoint.sh
RUN chmod +x /usr/local/bin/telemetry_entrypoint.sh

ENTRYPOINT ["/usr/local/bin/telemetry_entrypoint.sh"]

