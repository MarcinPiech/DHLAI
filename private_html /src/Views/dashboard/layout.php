<!DOCTYPE html>
<html lang="pl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>APM Automation - Panel</title>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
			background: #f5f5f5;
			padding: 20px;
		}
		.container {
			max-width: 1400px;
			margin: 0 auto;
			background: white;
			border-radius: 8px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		}
		header {
			padding: 20px 30px;
			border-bottom: 2px solid #e0e0e0;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			border-radius: 8px 8px 0 0;
		}
		header h1 { font-size: 28px; margin-bottom: 5px; }
		header p { opacity: 0.9; }
		
		.content { padding: 30px; }
		
		.upload-section {
			background: #f9f9f9;
			padding: 25px;
			border-radius: 6px;
			margin-bottom: 30px;
			border: 2px dashed #ccc;
		}
		.upload-section h2 {
			margin-bottom: 15px;
			color: #333;
			font-size: 20px;
		}
		.form-group {
			margin-bottom: 15px;
		}
		label {
			display: block;
			margin-bottom: 5px;
			font-weight: 600;
			color: #555;
		}
		input[type="text"], input[type="file"], select {
			width: 100%;
			padding: 10px;
			border: 1px solid #ddd;
			border-radius: 4px;
			font-size: 14px;
		}
		button {
			background: #667eea;
			color: white;
			border: none;
			padding: 12px 24px;
			border-radius: 4px;
			cursor: pointer;
			font-size: 14px;
			font-weight: 600;
			transition: background 0.2s;
		}
		button:hover {
			background: #5568d3;
		}
		button:disabled {
			background: #ccc;
			cursor: not-allowed;
		}
		
		.weeks-list {
			margin-top: 30px;
		}
		.weeks-list h2 {
			margin-bottom: 20px;
			color: #333;
		}
		.week-card {
			background: white;
			border: 1px solid #e0e0e0;
			border-radius: 6px;
			padding: 20px;
			margin-bottom: 15px;
			transition: box-shadow 0.2s;
		}
		.week-card:hover {
			box-shadow: 0 4px 12px rgba(0,0,0,0.1);
		}
		.week-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 15px;
		}
		.week-title {
			font-size: 20px;
			font-weight: 700;
			color: #333;
		}
		.week-status {
			display: inline-block;
			padding: 4px 12px;
			border-radius: 12px;
			font-size: 12px;
			font-weight: 600;
		}
		.status-draft { background: #fff3cd; color: #856404; }
		.status-sent { background: #d4edda; color: #155724; }
		.status-updated { background: #d1ecf1; color: #0c5460; }
		
		.week-actions {
			display: flex;
			gap: 10px;
			margin-top: 15px;
		}
		.week-actions button {
			padding: 8px 16px;
			font-size: 13px;
		}
		.btn-secondary {
			background: #6c757d;
		}
		.btn-secondary:hover {
			background: #5a6268;
		}
		.btn-danger {
			background: #dc3545;
		}
		.btn-danger:hover {
			background: #c82333;
		}
		
		.alert {
			padding: 15px;
			border-radius: 4px;
			margin-bottom: 20px;
		}
		.alert-success {
			background: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}
		.alert-error {
			background: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}
		.loading {
			text-align: center;
			padding: 40px;
			color: #666;
		}
	</style>
</head>
<body>
	<div class="container">
		<header>
			<h1>🚀 APM Automation System</h1>
			<p>System automatyzacji komunikacji i planowania tras DHL</p>
		</header>

		<div class="content">
			<!-- Upload Section -->
			<div class="upload-section">
				<h2>📤 Wczytaj plan tras</h2>
				<form id="uploadForm">
					<div class="form-group">
						<label>Numer tygodnia (np. T35)</label>
						<input type="text" id="weekNumber" name="week_number" placeholder="T35" required>
					</div>
					<div class="form-group">
						<label>Typ pliku</label>
						<select id="fileType" name="type">
							<option value="plan">Plan tras DHL</option>
							<option value="bags">BAG DHL - WYWÓZ</option>
						</select>
					</div>
					<div class="form-group">
						<label>Plik Excel</label>
						<input type="file" name="file" accept=".xlsx,.xls" required>
					</div>
					<button type="submit">Wczytaj plik</button>
				</form>
			</div>

			<!-- Alert Box -->
			<div id="alertBox" style="display:none;"></div>

			<!-- Weeks List -->
			<div class="weeks-list">
				<h2>📋 Lista tygodni</h2>
				<div id="weeksList" class="loading">Ładowanie...</div>
			</div>
		</div>
	</div>

	<script>
		// API base URL
		const API_URL = '/api';

		// Load weeks on page load
		document.addEventListener('DOMContentLoaded', () => {
			loadWeeks();
		});

		// Upload form handler
		document.getElementById('uploadForm').addEventListener('submit', async (e) => {
			e.preventDefault();
			
			const formData = new FormData(e.target);
			const button = e.target.querySelector('button');
			
			button.disabled = true;
			button.textContent = 'Przetwarzanie...';
			
			try {
				const response = await fetch(`${API_URL}/weeks/upload`, {
					method: 'POST',
					body: formData
				});
				
				const result = await response.json();
				
				if (result.success) {
					showAlert('success', result.message);
					e.target.reset();
					loadWeeks();
				} else {
					showAlert('error', result.error);
				}
			} catch (error) {
				showAlert('error', 'Błąd połączenia: ' + error.message);
			} finally {
				button.disabled = false;
				button.textContent = 'Wczytaj plik';
			}
		});

		// Load weeks list
		async function loadWeeks() {
			try {
				const response = await fetch(`${API_URL}/weeks`);
				const result = await response.json();
				
				if (result.success && result.weeks.length > 0) {
					renderWeeks(result.weeks);
				} else {
					document.getElementById('weeksList').innerHTML = '<p>Brak tygodni do wyświetlenia</p>';
				}
			} catch (error) {
				document.getElementById('weeksList').innerHTML = '<p>Błąd ładowania danych</p>';
			}
		}

		// Render weeks
		function renderWeeks(weeks) {
			const html = weeks.map(week => `
				<div class="week-card">
					<div class="week-header">
						<div>
							<span class="week-title">${week.week_number} / ${week.year}</span>
							<span class="week-status status-${week.status}">${week.status}</span>
						</div>
						<small>${week.uploaded_at || 'Brak daty'}</small>
					</div>
					<div class="week-actions">
						<button onclick="generateDrafts(${week.id})">Generuj drafty</button>
						<button class="btn-secondary" onclick="viewDetails(${week.id})">Szczegóły</button>
						${week.status === 'draft' ? `<button onclick="sendWeek(${week.id})">Wyślij maile</button>` : ''}
						<button class="btn-danger" onclick="deleteWeek(${week.id})">Usuń</button>
					</div>
				</div>
			`).join('');
			
			document.getElementById('weeksList').innerHTML = html;
		}

		// Generate drafts
		async function generateDrafts(weekId) {
			if (!confirm('Wygenerować drafty maili dla tego tygodnia?')) return;
			
			try {
				const response = await fetch(`${API_URL}/weeks/${weekId}/drafts`, {
					method: 'POST'
				});
				const result = await response.json();
				
				if (result.success) {
					showAlert('success', result.message);
					loadWeeks();
				} else {
					showAlert('error', result.error);
				}
			} catch (error) {
				showAlert('error', 'Błąd: ' + error.message);
			}
		}

		// Send week
		async function sendWeek(weekId) {
			if (!confirm('Wysłać wszystkie maile dla tego tygodnia?')) return;
			
			try {
				// First approve
				await fetch(`${API_URL}/weeks/${weekId}/drafts/approve`, {
					method: 'POST'
				});
				
				// Then send
				const response = await fetch(`${API_URL}/weeks/${weekId}/send`, {
					method: 'POST'
				});
				const result = await response.json();
				
				if (result.success) {
					showAlert('success', `Wysłano ${result.sent} maili`);
					loadWeeks();
				} else {
					showAlert('error', result.error);
				}
			} catch (error) {
				showAlert('error', 'Błąd: ' + error.message);
			}
		}

		// View details
		function viewDetails(weekId) {
			window.location.href = `/week/${weekId}`;
		}

		// Delete week
		async function deleteWeek(weekId) {
			if (!confirm('Usunąć ten tydzień? Operacja jest nieodwracalna!')) return;
			
			try {
				const response = await fetch(`${API_URL}/weeks/${weekId}`, {
					method: 'DELETE'
				});
				const result = await response.json();
				
				if (result.success) {
					showAlert('success', result.message);
					loadWeeks();
				} else {
					showAlert('error', result.error);
				}
			} catch (error) {
				showAlert('error', 'Błąd: ' + error.message);
			}
		}

		// Show alert
		function showAlert(type, message) {
			const alertBox = document.getElementById('alertBox');
			alertBox.className = `alert alert-${type}`;
			alertBox.textContent = message;
			alertBox.style.display = 'block';
			
			setTimeout(() => {
				alertBox.style.display = 'none';
			}, 5000);
		}
	</script>
</body>
</html>