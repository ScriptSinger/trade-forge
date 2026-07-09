import ccxt
from datetime import timezone, timedelta

# =========================
# НАСТРОЙКИ (ВСТАВЬ СВОИ ДАННЫЕ)
## =========================
API_KEY = ''
SECRET = ''

TG_TOKEN = ''
TG_CHAT_ID = '1058741192'

# =========================
# СТРАТЕГИЧЕСКИЕ ПАРАМЕТРЫ
# =========================
TIMEFRAME = '1h'
GLOBAL_TF = '1h'
RISK = 0.01             # 2% риска на сделку
TP_MULT = 2.5           # Оптимальный Тейк-Профит
SL_MULT = 2.0           # Стоп-Лосс
MAX_POSITIONS = 3       # Максимум одновременных позиций
ADX_THRESHOLD = 25      # Фильтр флэта
UPDATE_INTERVAL = 7200  # Обновление списка монет (2 часа)

# --- НАСТРОЙКИ ФИКСАЦИИ ПРИБЫЛИ / УБЫТКА (ЗА ДЕНЬ) ---
# Разные планки для тренда и флэта. Работают ВСЕГДА, независимо от статуса BTC.
# is_btc_ok (BTC выше EMA200) используется только чтобы выбрать, какая планка активна.
DAILY_PROFIT_TARGET_FLAT_PCT = 2.0    # Цель профита во флэте (BTC ниже EMA200)
DAILY_PROFIT_TARGET_TREND_PCT = 2.7   # Цель профита в тренде (BTC выше EMA200)
DAILY_LOSS_LIMIT_PCT = 2.5            # Дневной лимит убытка — стоп новым входам

# --- НАСТРОЙКИ ВХОДА ---
USE_PULLBACK_ENTRY = False   # True = ждать откат после пробоя перед входом, False = входить сразу на пробое
PULLBACK_ENTRY_PCT = 0.997   # Ждём отката на 0.3% от цены пробоя (используется только если USE_PULLBACK_ENTRY = True)
PULLBACK_TIMEOUT_BARS = 3    # Если за 3 бара (45 мин на 15m) отката не было — сигнал сгорает

POSITIONS_FILE = 'positions_pro.json'
CSV_LOG_FILE = 'trades_log_pro.csv'
TZ_EKAT = timezone(timedelta(hours=5))

# =========================
# ИНИЦИАЛИЗАЦИЯ БИРЖИ
# =========================
exchange = ccxt.bybit({
    'apiKey': API_KEY,
    'secret': SECRET,
    'enableRateLimit': True,
    'options': {'defaultType': 'spot'}
})
