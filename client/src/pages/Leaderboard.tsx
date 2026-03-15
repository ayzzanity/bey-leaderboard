import { useEffect, useState } from 'react';
import type { Player } from '../types/Player';
import { getLeaderboard } from '../api/api';

import LeaderboardTable from '../components/LeaderboardTable';

export default function Leaderboard() {
  const [players, setPlayers] = useState<Player[]>([]);

  useEffect(() => {
    loadLeaderboard();
  }, []);

  const loadLeaderboard = async () => {
    const data = await getLeaderboard();
    console.log('Leaderboard data:', data);
    setPlayers(data);
  };

  return (
    <div className="container">
      <h1>Global Leaderboard</h1>

      <LeaderboardTable players={players} />
    </div>
  );
}
