import { useEffect, useState } from 'react';
import { getLeaderboard } from '../api/api';

import { LeaderboardTable } from '../components';

export default function Dashboard() {
  const [players, setPlayers] = useState([]);

  useEffect(() => {
    loadLeaderboard();
  }, []);

  const loadLeaderboard = async () => {
    const data = await getLeaderboard();
    setPlayers(data);
  };

  return (
    <div className="container">
      <h1>Global Leaderboard</h1>

      <LeaderboardTable players={players} />
    </div>
  );
}
