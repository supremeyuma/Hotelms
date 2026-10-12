// Learn more about Tauri commands at https://tauri.app/develop/calling-rust/
pub mod envfile;
pub mod postgres;
pub mod runtime;
pub mod setup;

#[tauri::command]
fn greet(name: &str) -> String {
    format!("Hello, {}! You've been greeted from Rust!", name)
}

/// The ordered steps for a role, so the wizard can render them before running.
#[tauri::command]
fn setup_plan(role: &str) -> Result<Vec<setup::StepView>, String> {
    let role = match role {
        "server" => setup::SetupRole::Server,
        "terminal" => setup::SetupRole::Terminal,
        "staff_client" => setup::SetupRole::StaffClient,
        other => return Err(format!("unknown setup role: {other}")),
    };

    Ok(setup::plan_views(role))
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .invoke_handler(tauri::generate_handler![greet, setup_plan])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
