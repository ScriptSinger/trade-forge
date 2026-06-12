import ccxt
from datetime import timezone, timedelta

# =========================
# ?? НАСТРОЙКИ (ВСТАВЬ СВОИ ДАННЫЕ)
# =========================
API_KEY = ''
SECRET = ''

TG_TOKEN = ''  
TG_CHAT_ID = ''

# =========================
# ?? СТРАТЕГИЧЕСКИЕ ПАРАМЕТРЫ
# =========================
TIMEFRAME = '15m'
GLOBAL_TF = '1h'
RISK = 0.02             # 2% риска на сделку
TP_MULT = 3           # Оптимальный Тейк-Профит
SL_MULT = 2.0           # Стоп-Лосс
MAX_POSITIONS = 3       # Максимум одновременных позиций
ADX_THRESHOLD = 25      # Фильтр флэта
UPDATE_INTERVAL = 7200  # Обновление списка монет (2 часа)
# --- НАСТРОЙКИ ФИКСАЦИИ ПРИБЫЛИ ---
DAILY_PROFIT_TARGET_PCT = 2.3  # Дневной лимит профита в % для боковика

POSITIONS_FILE = '/root/azamat/positions_pro.json'
CSV_LOG_FILE = '/root/azamat/trades_log_pro.csv'
TZ_EKAT = timezone(timedelta(hours=5))

# =========================
# ?? ИНИЦИАЛИЗАЦИЯ БИРЖИ
# =========================
exchange = ccxt.bybit({
    'apiKey': API_KEY,
    'secret': SECRET,
    'enableRateLimit': True,
    'options': {'defaultType': 'spot'}
})