pub mod health;
pub mod iss_handler;
pub mod osdr_handler;
pub mod nasa_handler;
pub mod jwst_handler;
pub mod spacex_handler;

pub use health::health_check;
pub use iss_handler::{get_last_position, fetch_position, get_history, SharedIssService};
pub use osdr_handler::{sync_datasets, list_datasets, SharedOsdrService};
pub use nasa_handler::{get_apod, get_neo, get_donki_flr, get_donki_cme, SharedNasaService};
pub use jwst_handler::{get_images, SharedJwstService};
pub use spacex_handler::{get_next_launch, SharedSpaceXService};