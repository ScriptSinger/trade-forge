import pandas as pd
import numpy as np
from config_pro import exchange, GLOBAL_TF

def calculate_adx(df, period=14):
    df = df.copy()
    high = df['high']; low = df['low']; close = df['close']
    plus_dm = high.diff().clip(lower=0)
    minus_dm = (-low.diff()).clip(lower=0)
    plus_dm.loc[plus_dm < minus_dm] = 0
    minus_dm.loc[minus_dm < plus_dm] = 0
    tr = pd.concat([high - low, (high - close.shift()).abs(), (low - close.shift()).abs()], axis=1).max(axis=1)
    atr_smooth = tr.rolling(window=period).mean()
    plus_di = 100 * (plus_dm.rolling(window=period).mean() / atr_smooth)
    minus_di = 100 * (minus_dm.rolling(window=period).mean() / atr_smooth)
    dx = 100 * (plus_di - minus_di).abs() / (plus_di + minus_di)
    return dx.rolling(window=period).mean()

# ==========================================
# Расчет RSI (Индекс относительной силы)
# ==========================================
def calculate_rsi(series, period=14):
    delta = series.diff()
    gain = (delta.where(delta > 0, 0)).fillna(0)
    loss = (-delta.where(delta < 0, 0)).fillna(0)

    # Сглаживание Уайлдера (стандарт TradingView)
    avg_gain = gain.ewm(alpha=1/period, adjust=False).mean()
    avg_loss = loss.ewm(alpha=1/period, adjust=False).mean()

    rs = avg_gain / avg_loss
    return 100 - (100 / (1 + rs))

# ИСПРАВЛЕНИЕ: limit=1000 для идеального "прогрева" EMA200
def fetch(symbol, tf, limit=1000):
    try:
        ohlcv = exchange.fetch_ohlcv(symbol, timeframe=tf, limit=limit)
        df = pd.DataFrame(ohlcv, columns=['ts','open','high','low','close','vol'])
        df['ema50'] = df['close'].ewm(span=50).mean()
        df['ema200'] = df['close'].ewm(span=200).mean()
        df['vol_ma'] = df['vol'].rolling(20).mean()
        df['resistance'] = df['high'].rolling(20).max()
        df['prev_close'] = df['close'].shift(1)
        df['tr'] = np.maximum(df['high'], df['prev_close']) - np.minimum(df['low'], df['prev_close'])
        df['atr'] = df['tr'].rolling(14).mean()
        df['adx'] = calculate_adx(df)
        df['rsi'] = calculate_rsi(df['close']) # <-- ВНЕДРЕН ИНДИКАТОР RSI
        return df.dropna()
    except: return None

def btc_ok():
    df = fetch('BTC/USDT', GLOBAL_TF)
    if df is None: return False
    last = df.iloc[-1]
    return last['ema50'] > last['ema200']
