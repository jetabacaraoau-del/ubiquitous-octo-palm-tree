import warnings
warnings.filterwarnings("ignore")
import pandas as pd
import numpy as np
from sklearn.linear_model import LinearRegression
from datetime import datetime, timedelta
import json
import os
import sys

# CSV path relative to script location
csv_path = os.path.join(os.path.dirname(__file__), 'data', 'sales_data.csv')

if not os.path.exists(csv_path):
    print(f"CSV file not found: {csv_path}", file=sys.stderr)
    sys.exit(1)

# Load CSV with week,sales columns
df = pd.read_csv(csv_path)

# Check loaded data (for debugging, comment out after confirmed working)
#print(df.head(), file=sys.stderr)

# Convert 'week' like '2025-21' to a datetime representing the Monday of that week
def week_to_date(week_str):
    year, week = week_str.split('-')
    return datetime.strptime(f'{year}-{week}-1', "%G-%V-%u")

df['week_start'] = df['week'].apply(week_to_date)

# Sort by week_start date to ensure proper order
df = df.sort_values('week_start').reset_index(drop=True)

# Create numeric index for regression (0,1,2,...)
df['week_index'] = np.arange(len(df))

X = df[['week_index']]
y = df['sales']

# Fit linear regression model
model = LinearRegression()
model.fit(X, y)

# Predict next 6 weeks
n_future = 6
future_indices = np.arange(len(df), len(df) + n_future).reshape(-1, 1)
predictions = model.predict(future_indices).round().astype(int)

# Generate future week labels 'YYYY-WW'
last_week_date = df['week_start'].iloc[-1]
future_weeks = [(last_week_date + timedelta(weeks=i+1)).strftime("%G-%V") for i in range(n_future)]

# Output JSON dictionary week -> predicted sales
output = dict(zip(future_weeks, predictions.tolist()))

print(json.dumps(output))
