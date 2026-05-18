# Live Cricket Scoring System - Documentation

A comprehensive cricket scoring and fantasy points management system built for CricketPoints.

## Features

### 1. Match Setup Panel (`admin/scoring/setup.php`)
Admin interface to configure match details:
- Select batting and bowling teams
- Choose striker, non-striker, and bowler
- Start match with configured parameters
- Team-based player dropdown filters

### 2. Live Ball Entry Panel (`admin/scoring/live_scoring.php`)
Real-time ball-by-ball scoring interface with:
- **Scoring Buttons**: 0, 1, 2, 3, 4, 6 runs
- **Extra Runs**: Wide, No Ball, Bye, Leg Bye
- **Wicket Recording**: Multiple wicket types with fielder selection
- **Match Controls**: Undo last ball, end over, end innings
- **Live Scoreboard**: Real-time updates (auto-refresh every 2 seconds)
- **Activity Log**: Recent ball history

### 3. Live Scoreboard Display (`match_scoreboard.php`)
Public scoreboard showing:
- Current team score, wickets, overs
- Run rate calculation
- Striker and non-striker stats (runs, balls, strike rate, 4s, 6s)
- Current bowler stats (overs, runs, wickets, economy)
- Batting and bowling lineups
- Last 6 balls timeline with color-coded display
- Mobile-responsive design

### 4. Player Performance Page (`player_match_stats.php`)
Individual player statistics showing:
- Batting stats: runs, balls faced, strike rate, fours, sixes, status
- Bowling stats: overs, runs conceded, wickets, economy, maiden overs
- Fielding stats: catches, run-outs, stumpings
- **Fantasy Points Breakdown**:
  - Batting points
  - Bowling points
  - Fielding points
  - Bonus points
- Fantasy points leaderboard

### 5. Dream11 Fantasy Points System

#### Batting Points
| Metric | Points |
|--------|--------|
| Per run | +1 |
| Boundary (4) | +1 bonus |
| Six (6) | +2 bonus |
| Half-century (50) | +8 bonus |
| Century (100) | +16 bonus |
| Duck (0 & out) | -2 |
| Strike rate > 170 | +6 |
| Strike rate 150-170 | +4 |
| Strike rate 130-150 | +2 |

#### Bowling Points
| Metric | Points |
|--------|--------|
| Per wicket | +25 |
| 3 wickets | +8 bonus |
| 5 wickets | +16 bonus |
| Maiden over | +12 |
| Economy < 5 | +6 |
| Economy 5-6 | +4 |
| Economy 6-7 | +2 |

#### Fielding Points
| Metric | Points |
|--------|--------|
| Catch | +8 |
| Run-out | +12 |
| Stumping | +12 |

## API Endpoints

### `api/ball_entry.php`
Handles all ball entry operations
- **Actions**: 
  - `record_ball`: Record regular run
  - `wicket_details`: Record wicket with fielder info
  - `undo_last_ball`: Revert last ball
  - `end_over`: Mark over as complete
  - `end_innings`: End innings
  - `update_strike`: Swap striker/non-striker

### `api/match_state.php`
GET endpoint returning live match data
- **Parameters**: `match_id`, `innings`
- **Returns**: Score, batsmen info, bowler info, last 6 balls, lineups

### `api/player_stats.php`
GET endpoint for individual player stats
- **Parameters**: `match_id`, `player_id`, `innings`
- **Returns**: Batting, bowling, fielding stats and fantasy points

### `api/get_team_players.php`
GET endpoint for team roster
- **Parameters**: `team_id`
- **Returns**: List of players in team

## Database Structure

### New/Modified Tables

#### `matches` (Modified)
```
batting_team_id INT UNSIGNED
bowling_team_id INT UNSIGNED
total_overs INT (default 20)
status ENUM('setup','live','innings_break','completed')
current_innings TINYINT
```

#### `player_match_stats` (Modified)
```
balls_faced INT
strike_rate DECIMAL(6,2)
economy DECIMAL(6,2)
balls_bowled INT
runs_conceded INT
innings_number TINYINT
is_out TINYINT(1)
fantasy_points INT
```

#### `ball_by_ball` (New)
Complete ball-by-ball tracking with:
- Bowler, striker, non-striker IDs
- Runs and extras
- Wicket information
- Fielder involved

#### `player_points` (New)
Fantasy points calculation table:
- `match_id`, `player_id`, `innings`
- `batting_pts`, `bowling_pts`, `fielding_pts`, `bonus_pts`
- `total_pts`

## Auto Features

### 1. Strike Change
- **Odd Runs**: Automatically swaps striker after 1, 3, 5 runs
- **Over Completion**: Auto-swap at end of over (after 6 balls)

### 2. Fantasy Points Auto-Calculation
- Points recalculated after every ball
- Updates in real-time
- Bonus points applied automatically

### 3. Live Scoreboard Updates
- Auto-refresh every 2-3 seconds
- AJAX-based, no page reload
- Real-time stats for all players

### 4. Over/Innings Tracking
- Auto-increment overs
- Ball counter resets after 6 balls
- Maiden over detection

## Usage Guide

### For Admins - Starting a Match

1. Go to **Admin > Match Setup**
2. Select the match from dropdown
3. Choose **Batting Team** (shows players auto-filtered)
4. Choose **Bowling Team** (shows players auto-filtered)
5. Select **Striker** from batting team
6. Select **Non-Striker** from batting team
7. Select **Bowler** from bowling team
8. Click **Start Match**

### For Admins - Recording Runs

1. Go to **Admin > Live Scoring**
2. Select Striker, Non-Striker, Bowler
3. Click scoring button (0-6, Width, No Ball, etc.)
4. Ball automatically records with stats update
5. Use **Undo** if needed
6. Use **End Over** to complete over (auto after 6 balls)

### For Admins - Recording Wickets

1. Click **Wicket** button
2. Select wicket type (Bowled, Caught, LBW, Run Out, Stumped)
3. If required, select fielder involved
4. Enter any extra runs
5. Submit

### For Players - Viewing Stats

1. Go to **player_match_stats.php?match_id=X&player_id=Y**
2. See all stats including fantasy points
3. View points breakdown
4. Check leaderboard

## Mobile Responsiveness

All pages are fully responsive:
- Scoreboard adapts to screens
- Buttons scale appropriately
- Stats grid responsive
- Touch-optimized button sizes

## Folder Structure

```
/admin/scoring/
    setup.php          - Match configuration
    live_scoring.php   - Ball entry panel

/api/
    ball_entry.php           - Ball record operations
    match_state.php          - Match data API
    player_stats.php         - Player stats API
    get_team_players.php     - Team roster API

/database/
    cricket_db.php           - Cricket-specific DB functions
    points_calculator.php    - Fantasy points calculation

/migrations/
    upgrade_20260401_live_scoring.sql - Database setup

match_scoreboard.php          - Public scoreboard
player_match_stats.php        - Player performance page
```

## Key Functions

### In `database/cricket_db.php`:
- `get_team_players()` - Get players by team
- `get_match_details()` - Get match info
- `get_match_score()` - Get current score
- `get_batsman_stats()` - Get player batting stats
- `get_bowler_stats()` - Get player bowling stats
- `get_last_6_balls()` - Get ball timeline
- `record_ball()` - Insert new ball record
- `get_next_ball_position()` - Calculate next ball number

### In `database/points_calculator.php`:
- `calculate_batsman_points()` - Calculate batting fantasy points
- `calculate_bowler_points()` - Calculate bowling fantasy points
- `calculate_fielding_points()` - Calculate fielding fantasy points
- `recalculate_and_update_player_points()` - Update all points for player

## Installation

1. Run migration SQL file:
   ```sql
   mysql -u root cricket_points < migrations/upgrade_20260401_live_scoring.sql
   ```

2. Verify all files are in place:
   - api/ball_entry.php
   - api/match_state.php
   - api/player_stats.php
   - api/get_team_players.php
   - admin/scoring/setup.php
   - admin/scoring/live_scoring.php
   - database/cricket_db.php
   - database/points_calculator.php
   - match_scoreboard.php
   - player_match_stats.php

3. Access admin panel:
   - Browser: `http://localhost/CricketPoints/admin.php`
   - Navigate to "Match Setup" and "Live Scoring"

## Notes

- All times are in 24-hour format
- All database queries use prepared statements (SQL injection safe)
- Points are recalculated after every ball for accuracy
- The system supports multiple innings
- Wickets count as 0 runs to striker (already handled in record_ball)
- Economy rate calculated as (runs_conceded / overs)

## Future Enhancements

- WebSocket for true real-time updates
- Multiple live matches simultaneously
- Match replay functionality
- Advanced statistics and trends
- Player rating system
- Team vs Team comparison
- Export scorecards as PDF
