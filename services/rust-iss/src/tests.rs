#[cfg(test)]
mod tests {
    use chrono::{TimeZone, Utc};
    use serde_json::json;

    use crate::services::{haversine_km, normalize_osdr_items, s_pick, t_pick};

    #[test]
    fn pick_string_and_time() {
        let v = json!({"id":"OSD-1","updated_at":"2025-01-01T00:00:00Z"});
        assert_eq!(s_pick(&v, &["id"]), Some("OSD-1".to_string()));
        let t = t_pick(&v, &["updated_at"]).unwrap();
        assert_eq!(t, Utc.with_ymd_and_hms(2025,1,1,0,0,0).unwrap());
    }

    #[test]
    fn normalize_osdr_uses_business_key() {
        let v = json!([{"dataset_id":"OSD-1","title":"x","status":"ok","updated":"2025-01-02T03:04:05Z"}]);
        let items = normalize_osdr_items(&v);
        assert_eq!(items.len(), 1);
        assert_eq!(items[0].dataset_id.as_deref(), Some("OSD-1"));
        assert_eq!(items[0].title.as_deref(), Some("x"));
        assert_eq!(items[0].status.as_deref(), Some("ok"));
    }

    #[test]
    fn haversine_distance_non_zero() {
        let d = haversine_km(55.0, 37.0, 55.1, 37.2);
        assert!(d > 0.0);
    }

    #[test]
    fn s_pick_prefers_first_key_and_numbers() {
        let v = json!({"b":"second","a":"first","n":42});
        assert_eq!(s_pick(&v, &["a", "b"]).as_deref(), Some("first"));
        assert_eq!(s_pick(&v, &["n"]).as_deref(), Some("42"));
    }

    #[test]
    fn t_pick_handles_unix_timestamp_and_fallback_format() {
        let v = json!({"ts": 1_600_000_000, "dt": "2025-05-06 07:08:09"});
        let t1 = t_pick(&v, &["ts"]).unwrap();
        assert_eq!(t1, Utc.timestamp_opt(1_600_000_000, 0).unwrap());
        let t2 = t_pick(&v, &["dt"]).unwrap();
        assert_eq!(t2, Utc.with_ymd_and_hms(2025, 5, 6, 7, 8, 9).unwrap());
    }

    #[test]
    fn normalize_osdr_accepts_results_and_items_arrays() {
        let data = json!({
          "results": [
            {"dataset_id":"R1","title":"res","status":"ok","updated_at":"2025-01-01T00:00:00Z"}
          ],
          "items": [
            {"id":"I1","name":"item","state":"ready","modified":"2025-02-02T00:00:00Z"}
          ]
        });
        let mut items = normalize_osdr_items(&data);
        items.sort_by_key(|x| x.dataset_id.clone());
        assert_eq!(items.len(), 2);
        assert_eq!(items[0].dataset_id.as_deref(), Some("I1"));
        assert_eq!(items[1].dataset_id.as_deref(), Some("R1"));
        assert_eq!(items[0].title.as_deref(), Some("item"));
        assert_eq!(items[1].title.as_deref(), Some("res"));
    }

    #[test]
    fn normalize_osdr_keeps_raw_payload() {
        let src = json!({"id":"X","title":"t","updated_at":"2025-03-04T05:06:07Z"});
        let items = normalize_osdr_items(&src);
        assert_eq!(items.len(), 1);
        assert_eq!(items[0].raw["id"], "X");
    }

    #[test]
    fn haversine_zero_distance() {
        let d = haversine_km(10.0, 20.0, 10.0, 20.0);
        assert!(d.abs() < 1e-9);
    }

    #[test]
    fn haversine_symmetric() {
        let a = haversine_km(1.0, 2.0, 3.0, 4.0);
        let b = haversine_km(3.0, 4.0, 1.0, 2.0);
        assert!((a - b).abs() < 1e-9);
    }

    #[test]
    fn haversine_real_world_distance() {
        // Москва (55.7558, 37.6176) -> Нью-Йорк (40.7128, -74.0060)
        let d = haversine_km(55.7558, 37.6176, 40.7128, -74.0060);
        assert!(d > 7400.0 && d < 7600.0);
    }
}

