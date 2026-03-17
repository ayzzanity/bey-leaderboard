import { useEffect, useState } from 'react';
import { Card } from 'antd';
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
    <Card title="Global Leaderboard">
      <LeaderboardTable players={players} />
    </Card>
  );
}
