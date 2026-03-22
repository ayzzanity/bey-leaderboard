import { useEffect, useState } from 'react';
import { Card, Input, Tabs, Typography } from 'antd';
import { getLeaderboard } from '../api/api';

import { LeaderboardTable } from '../components';

export default function Leaderboard({
  pageTitle = 'ZC Pincers Leaderboard',
  cardTitle = null,
  showPageTitle = true,
}) {
  const [leaderboards, setLeaderboards] = useState({ open: [], junior: [] });
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    loadLeaderboard();
  }, []);

  const loadLeaderboard = async () => {
    const data = await getLeaderboard();
    setLeaderboards(data);
  };

  const filterPlayers = (players) => players.filter((player) => (
    player.name?.toLowerCase().includes(searchTerm.trim().toLowerCase())
  ));

  return (
    <div className="container">
      {showPageTitle && <h1>{pageTitle}</h1>}

      <Card title={cardTitle}>
        <Input
          className="mb-4"
          placeholder="Search player name"
          value={searchTerm}
          onChange={(event) => setSearchTerm(event.target.value)}
        />
        <Tabs
          defaultActiveKey="open"
          items={[
            {
              key: 'open',
              label: 'Open Cat',
              children: filterPlayers(leaderboards.open ?? []).length
                ? <LeaderboardTable players={filterPlayers(leaderboards.open ?? [])} />
                : <Typography.Text>No open category results yet.</Typography.Text>
            },
            {
              key: 'junior',
              label: 'Junior Cat',
              children: filterPlayers(leaderboards.junior ?? []).length
                ? <LeaderboardTable players={filterPlayers(leaderboards.junior ?? [])} />
                : <Typography.Text>No junior category results yet.</Typography.Text>
            }
          ]}
        />
      </Card>
    </div>
  );
}
