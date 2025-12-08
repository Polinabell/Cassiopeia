import csv
import os
import random
from datetime import datetime, timezone
from pathlib import Path

import psycopg2


def env(key: str, default: str) -> str:
    return os.environ.get(key, default)


def generate_csv(out_dir: Path) -> Path:
    out_dir.mkdir(parents=True, exist_ok=True)
    ts = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S")
    filename = f"telemetry_{ts}.csv"
    path = out_dir / filename

    recorded_at = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    voltage = round(random.uniform(3.2, 12.6), 2)
    temp = round(random.uniform(-50.0, 80.0), 2)

    with path.open("w", newline="") as f:
        writer = csv.DictWriter(
            f, fieldnames=["recorded_at", "voltage", "temp", "source_file"]
        )
        writer.writeheader()
        writer.writerow(
            {
                "recorded_at": recorded_at,
                "voltage": f"{voltage:.2f}",
                "temp": f"{temp:.2f}",
                "source_file": filename,
            }
        )
    print(f"[telemetry] generated {path}")
    return path


def copy_to_db(path: Path) -> None:
    conn = psycopg2.connect(
        host=env("PGHOST", "db"),
        port=env("PGPORT", "5432"),
        user=env("PGUSER", "monouser"),
        password=env("PGPASSWORD", "monopass"),
        dbname=env("PGDATABASE", "monolith"),
    )
    with conn, conn.cursor() as cur, path.open("r") as f:
        cur.copy_expert(
            "COPY telemetry_legacy(recorded_at, voltage, temp, source_file) FROM STDIN WITH (FORMAT csv, HEADER true)",
            f,
        )
    print(f"[telemetry] copied {path.name} into telemetry_legacy")


def main() -> None:
    out_dir = Path(env("CSV_OUT_DIR", "/data/csv"))
    csv_path = generate_csv(out_dir)
    try:
        copy_to_db(csv_path)
    except Exception as exc:  # noqa: BLE001
        print(f"[telemetry] failed to copy: {exc}")
        raise


if __name__ == "__main__":
    main()

