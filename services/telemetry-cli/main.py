"""
Telemetry CLI — генерация CSV-данных телеметрии с последующей загрузкой в PostgreSQL.

Формат CSV:
- timestamp (ISO 8601)
- boolean (ИСТИНА / ЛОЖЬ)
- numbers (числовой формат)
- strings (текст)
"""

import csv
import os
import random
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import psycopg2


def env(key: str, default: str) -> str:
    return os.environ.get(key, default)


def format_bool(value: bool) -> str:
    """Форматирование булевых значений: ИСТИНА / ЛОЖЬ."""
    return "ИСТИНА" if value else "ЛОЖЬ"


def format_number(value: float, decimals: int = 2) -> str:
    """Форматирование чисел с фиксированным количеством знаков после запятой."""
    return f"{value:.{decimals}f}"


def format_timestamp(dt: datetime) -> str:
    """Форматирование времени в ISO 8601."""
    return dt.strftime("%Y-%m-%dT%H:%M:%S%z")


def generate_telemetry_row() -> dict[str, Any]:
    """Генерация одной строки телеметрии."""
    now = datetime.now(timezone.utc)
    voltage = round(random.uniform(3.2, 12.6), 2)
    temp = round(random.uniform(-50.0, 80.0), 2)
    
    # Логические значения: валидность показаний
    voltage_valid = 3.0 <= voltage <= 15.0
    temp_valid = -60.0 <= temp <= 100.0
    overall_valid = voltage_valid and temp_valid
    
    # Статус системы (строка)
    if overall_valid:
        status = "NOMINAL"
    elif not voltage_valid:
        status = "VOLTAGE_ALERT"
    else:
        status = "TEMP_ALERT"
    
    return {
        "recorded_at": now,
        "voltage": voltage,
        "temp": temp,
        "voltage_valid": voltage_valid,
        "temp_valid": temp_valid,
        "overall_valid": overall_valid,
        "status": status,
        "sensor_id": f"SENSOR_{random.randint(1, 10):02d}",
        "mission_day": random.randint(1, 365),
    }


def generate_csv(out_dir: Path) -> Path:
    """
    Генерация CSV файла с правильным форматированием:
    - timestamp: ISO 8601
    - boolean: ИСТИНА / ЛОЖЬ
    - numbers: числовой формат
    - strings: текст
    """
    out_dir.mkdir(parents=True, exist_ok=True)
    ts = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S")
    filename = f"telemetry_{ts}.csv"
    path = out_dir / filename

    row = generate_telemetry_row()
    
    # CSV для PostgreSQL COPY (упрощённый, только нужные поля)
    simple_path = out_dir / f"telemetry_simple_{ts}.csv"
    with simple_path.open("w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(
            f, fieldnames=["recorded_at", "voltage", "temp", "source_file"]
        )
        writer.writeheader()
        writer.writerow({
            "recorded_at": row["recorded_at"].strftime("%Y-%m-%d %H:%M:%S"),
            "voltage": format_number(row["voltage"]),
            "temp": format_number(row["temp"]),
            "source_file": filename,
        })
    
    # Полный CSV с форматированием
    fieldnames = [
        "timestamp",           # ISO 8601
        "voltage",             # число
        "temp",                # число
        "voltage_valid",       # ИСТИНА/ЛОЖЬ
        "temp_valid",          # ИСТИНА/ЛОЖЬ
        "overall_valid",       # ИСТИНА/ЛОЖЬ
        "status",              # текст
        "sensor_id",           # текст
        "mission_day",         # число
        "source_file",         # текст
    ]
    
    with path.open("w", newline="", encoding="utf-8") as f:
        # BOM для Excel
        f.write("\ufeff")
        writer = csv.DictWriter(f, fieldnames=fieldnames, delimiter=";")
        writer.writeheader()
        writer.writerow({
            "timestamp": format_timestamp(row["recorded_at"]),
            "voltage": format_number(row["voltage"]),
            "temp": format_number(row["temp"]),
            "voltage_valid": format_bool(row["voltage_valid"]),
            "temp_valid": format_bool(row["temp_valid"]),
            "overall_valid": format_bool(row["overall_valid"]),
            "status": row["status"],
            "sensor_id": row["sensor_id"],
            "mission_day": str(row["mission_day"]),
            "source_file": filename,
        })
    
    print(f"[telemetry] generated {path}")
    print(f"[telemetry] generated {simple_path} (for DB COPY)")
    
    return simple_path  # Возвращаем простой CSV для загрузки в БД


def copy_to_db(path: Path) -> None:
    """Загрузка CSV в PostgreSQL через COPY."""
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
