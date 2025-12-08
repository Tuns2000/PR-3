#[cfg(test)]
mod tests {
    use super::super::*;
    use chrono::{NaiveDateTime, Utc};
    use validator::Validate;

    #[test]
    fn test_iss_position_creation() {
        let position = IssPosition {
            id: Some(1),
            latitude: 45.5,
            longitude: -122.6,
            altitude: 408.5,
            velocity: 27600.0,
            timestamp: NaiveDateTime::from_timestamp_opt(1638360000, 0).unwrap(),
            fetched_at: Utc::now(),
        };

        assert_eq!(position.latitude, 45.5);
        assert_eq!(position.longitude, -122.6);
        assert_eq!(position.altitude, 408.5);
        assert_eq!(position.velocity, 27600.0);
    }

    #[test]
    fn test_iss_position_latitude_range() {
        // Valid latitude: -90 to 90
        let position = IssPosition {
            id: None,
            latitude: 90.0,
            longitude: 0.0,
            altitude: 400.0,
            velocity: 27000.0,
            timestamp: NaiveDateTime::from_timestamp_opt(1638360000, 0).unwrap(),
            fetched_at: Utc::now(),
        };
        assert_eq!(position.latitude, 90.0);

        let position2 = IssPosition {
            id: None,
            latitude: -90.0,
            longitude: 0.0,
            altitude: 400.0,
            velocity: 27000.0,
            timestamp: NaiveDateTime::from_timestamp_opt(1638360000, 0).unwrap(),
            fetched_at: Utc::now(),
        };
        assert_eq!(position2.latitude, -90.0);
    }

    #[test]
    fn test_iss_history_query_validation() {
        // Valid query
        let query = IssHistoryQuery {
            limit: Some(100),
            start_date: Some(Utc::now() - chrono::Duration::days(7)),
            end_date: Some(Utc::now()),
        };
        assert!(query.validate().is_ok());

        // Invalid: limit too high
        let invalid_query = IssHistoryQuery {
            limit: Some(5000), // Max is 1000
            start_date: None,
            end_date: None,
        };
        assert!(invalid_query.validate().is_err());

        // Invalid: limit too low
        let invalid_query2 = IssHistoryQuery {
            limit: Some(0), // Min is 1
            start_date: None,
            end_date: None,
        };
        assert!(invalid_query2.validate().is_err());

        // Valid: no limit specified
        let query_no_limit = IssHistoryQuery {
            limit: None,
            start_date: None,
            end_date: None,
        };
        assert!(query_no_limit.validate().is_ok());
    }

    #[test]
    fn test_iss_api_response_deserialization() {
        let json = r#"{
            "latitude": 51.5074,
            "longitude": -0.1278,
            "altitude": 415.3,
            "velocity": 27580.5,
            "timestamp": 1638360000
        }"#;

        let response: IssApiResponse = serde_json::from_str(json).unwrap();
        assert_eq!(response.latitude, 51.5074);
        assert_eq!(response.longitude, -0.1278);
        assert_eq!(response.altitude, 415.3);
        assert_eq!(response.velocity, 27580.5);
        assert_eq!(response.timestamp, 1638360000);
    }

    #[test]
    fn test_osdr_dataset_creation() {
        let dataset = OsdrDataset {
            id: Some(1),
            dataset_id: "OSD-123".to_string(),
            title: "Mouse RNA-Seq Study".to_string(),
            description: Some("Gene expression analysis".to_string()),
            release_date: Some(NaiveDateTime::from_timestamp_opt(1638360000, 0).unwrap()),
            updated_at: NaiveDateTime::from_timestamp_opt(1638360000, 0).unwrap(),
        };

        assert_eq!(dataset.dataset_id, "OSD-123");
        assert_eq!(dataset.title, "Mouse RNA-Seq Study");
        assert!(dataset.description.is_some());
    }

    #[test]
    fn test_osdr_api_response_deserialization() {
        let json = r#"{
            "results": [
                {
                    "accession": "OSD-123",
                    "title": "Test Dataset",
                    "description": "Test Description"
                }
            ]
        }"#;

        let response: Result<OsdrApiResponse, _> = serde_json::from_str(json);
        assert!(response.is_ok());
        
        let osdr_response = response.unwrap();
        assert_eq!(osdr_response.results.len(), 1);
    }

    #[test]
    fn test_jwst_image_creation() {
        let image = JwstImage {
            id: Some(1),
            observation_id: "jw02731-001".to_string(),
            title: "JWST Deep Field".to_string(),
            description: Some("Deep space observation".to_string()),
            image_url: "https://example.com/image.png".to_string(),
            observation_date: Some(NaiveDateTime::from_timestamp_opt(1638360000, 0).unwrap()),
            instrument: Some("NIRCam".to_string()),
            updated_at: NaiveDateTime::from_timestamp_opt(1638360000, 0).unwrap(),
        };

        assert_eq!(image.observation_id, "jw02731-001");
        assert_eq!(image.instrument, Some("NIRCam".to_string()));
    }

    #[test]
    fn test_iss_position_serialization() {
        let position = IssPosition {
            id: Some(1),
            latitude: 45.5,
            longitude: -122.6,
            altitude: 408.5,
            velocity: 27600.0,
            timestamp: NaiveDateTime::from_timestamp_opt(1638360000, 0).unwrap(),
            fetched_at: Utc::now(),
        };

        let json = serde_json::to_string(&position);
        assert!(json.is_ok());

        let json_str = json.unwrap();
        assert!(json_str.contains("latitude"));
        assert!(json_str.contains("45.5"));
    }

    #[test]
    fn test_date_range_validation() {
        use chrono::Duration;
        
        let now = Utc::now();
        let start = now - Duration::days(7);
        let end = now;

        let query = IssHistoryQuery {
            limit: Some(100),
            start_date: Some(start),
            end_date: Some(end),
        };

        assert!(query.validate().is_ok());
        assert!(query.start_date.unwrap() < query.end_date.unwrap());
    }
}
