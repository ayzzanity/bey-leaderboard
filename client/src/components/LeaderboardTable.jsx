import { Link } from 'react-router-dom';
import { Table } from 'antd';

export default function LeaderboardTable({ players }) {
  return (
    <Table
      dataSource={players}
      columns={[
        { title: 'Rank', dataIndex: 'rank' },
        {
          title: 'Player',
          render: (_, record) => <Link to={`/players/${record.player_id}`}>{record.name}</Link>
        },
        { title: 'Points', dataIndex: 'points' },
        { title: 'Tournaments', dataIndex: 'tournaments' }
      ]}
    />
  );
}
